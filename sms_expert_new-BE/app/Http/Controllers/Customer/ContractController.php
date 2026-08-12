<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractSignature;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ContractController extends Controller
{
    /**
     * Get the current logged-in customer user
     */
    private function getCustomerUser()
    {
        $userInfo = session('user_info');
        
        if (!$userInfo || !isset($userInfo['bigid'])) {
            Log::warning('Contract access: No user_info in session');
            return null;
        }
        
        $user = User::where('bigid', $userInfo['bigid'])->first();
        
        if (!$user) {
            Log::warning('Contract access: User not found for bigid: ' . $userInfo['bigid']);
        }
        
        return $user;
    }

    /**
     * Display contracts for customer
     */
    public function index()
    {
        $user = $this->getCustomerUser();
        
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login to view contracts.');
        }
        
        // Debug: Log user info
        Log::info('Customer contracts page accessed', [
            'user_id' => $user->id,
            'bigid' => $user->bigid,
            'uname' => $user->uname
        ]);
        
        // Get active main contracts for this customer
        $mainContracts = Contract::main()
            ->active()
            ->forCustomer($user->id)
            ->get()
            ->map(function($contract) use ($user) {
                $contract->is_signed = $contract->isSignedByUser($user->id);
                $contract->signature = $contract->getUserSignature($user->id);
                return $contract;
            });
        
        // Debug: Log main contracts found
        Log::info('Main contracts found for customer', [
            'user_id' => $user->id,
            'count' => $mainContracts->count(),
            'contract_ids' => $mainContracts->pluck('id')->toArray()
        ]);
        
        // Get active addendums for this customer
        $addendums = Contract::addendum()
            ->active()
            ->forCustomer($user->id)
            ->get()
            ->map(function($contract) use ($user) {
                $contract->is_signed = $contract->isSignedByUser($user->id);
                $contract->signature = $contract->getUserSignature($user->id);
                return $contract;
            });
        
        // Debug: Log addendums found
        Log::info('Addendums found for customer', [
            'user_id' => $user->id,
            'count' => $addendums->count(),
            'contract_ids' => $addendums->pluck('id')->toArray()
        ]);

        // Get active privacy policies for this customer
        $privacyPolicies = Contract::privacyPolicy()
            ->active()
            ->forCustomer($user->id)
            ->get()
            ->map(function($contract) use ($user) {
                $contract->is_signed = $contract->isSignedByUser($user->id);
                $contract->signature = $contract->getUserSignature($user->id);
                return $contract;
            });
        
        // Debug: Log privacy policies found
        Log::info('Privacy policies found for customer', [
            'user_id' => $user->id,
            'count' => $privacyPolicies->count(),
            'contract_ids' => $privacyPolicies->pluck('id')->toArray()
        ]);

        // OLD-SYSTEM PARITY: the re-sign reason (set when a key profile element changed)
        // drives the generic "re-sign" form at the top of the page.
        $resignReason = DB::table('useroption')
            ->where('userref', $user->bigid)
            ->value('agreedcontracts_description');

        return view('customer.contracts.index', compact(
            'mainContracts',
            'addendums',
            'privacyPolicies',
            'resignReason'
        ));
    }

    /**
     * Generic contract RE-SIGN (mirrors the old ContractManager flow): when a key
     * profile element changed, the customer must re-agree before continuing. This
     * clears useroption.agreedcontracts_description and re-opens the dashboard,
     * even if the underlying contract was already signed.
     */
    public function resign(Request $request)
    {
        $user = $this->getCustomerUser();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login to continue.');
        }

        $validator = Validator::make($request->all(), [
            // Same rules as cp2_contracts.inc: full name with a space, valid email, position 3+.
            'signee_name' => ['required', 'string', 'min:5', 'max:255', 'regex:/\s/'],
            'signee_email' => 'required|email|max:255',
            'signee_position' => 'required|string|min:3|max:255',
            'iagree' => 'required',
        ], [
            'signee_name.required' => 'Please enter your full name.',
            'signee_name.min' => 'Please enter your full name (first and last).',
            'signee_name.regex' => 'Please enter your firstname and surname (with a space).',
            'signee_email.email' => 'Please enter a valid email address.',
            'signee_position.min' => 'Position must be at least 3 characters.',
            'iagree.required' => 'Please tick the box to confirm you agree.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('customer.contracts.index')
                ->withErrors($validator)
                ->withInput();
        }

        // Record/refresh a signature on the customer's main contract for the audit trail.
        $mainContract = Contract::main()->active()->forCustomer($user->id)->first();
        if ($mainContract) {
            ContractSignature::updateOrCreate(
                ['user_id' => $user->id, 'contract_id' => $mainContract->id],
                [
                    'signee_name'     => $request->signee_name,
                    'signee_email'    => $request->signee_email,
                    'signee_position' => $request->signee_position,
                    'signature_data'  => 'Re-signed after profile change',
                    'ip_address'      => $request->ip(),
                    'user_agent'      => $request->userAgent(),
                    'signed_at'       => now(),
                ]
            );
        }

        // OLD-SYSTEM PARITY (ContractManager::setUserAgreed): record agreement + clear
        // the re-sign reason so the gatekeeper re-opens the dashboard.
        DB::table('useroption')
            ->where('userref', $user->bigid)
            ->update([
                'agreedcontracts'             => now('Europe/London')->format('Y-m-d'),
                'agreedcontracts_description' => null,
            ]);

        Log::info('Contract re-signed (profile change)', [
            'user_id' => $user->id,
            'bigid'   => $user->bigid,
            'signee'  => $request->signee_name,
        ]);

        return redirect()->route('dashboard')->with('success', 'Thank you — your contract has been re-signed.');
    }

    /**
     * Show a specific contract
     */
    public function show($id)
    {
        $user = $this->getCustomerUser();
        
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login to view contracts.');
        }
        
        // Get contract that is active and available to this customer
        $contract = Contract::where('id', $id)
            ->active()
            ->forCustomer($user->id)
            ->first();
        
        if (!$contract) {
            Log::warning('Contract not found or not accessible', [
                'contract_id' => $id,
                'user_id' => $user->id
            ]);
            return redirect()->route('customer.contracts.index')
                ->with('error', 'Contract not found or you do not have permission to view it.');
        }
        
        $isSigned = $contract->isSignedByUser($user->id);
        $signature = $contract->getUserSignature($user->id);

        return view('customer.contracts.show', compact('contract', 'isSigned', 'signature'));
    }

    /**
     * Sign a contract
     */
    public function sign(Request $request, $id)
    {
        $user = $this->getCustomerUser();
        
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login to sign contracts.');
        }
        
        // Get contract that is active and available to this customer
        $contract = Contract::where('id', $id)
            ->active()
            ->forCustomer($user->id)
            ->first();

        if (!$contract) {
            return redirect()->route('customer.contracts.index')
                ->with('error', 'Contract not found or you do not have permission to sign it.');
        }

        // Check if contract requires signature
        if (!$contract->requires_signature) {
            return redirect()->back()->with('error', 'This contract does not require a signature.');
        }

        // Check if already signed
        if ($contract->isSignedByUser($user->id)) {
            return redirect()->back()->with('error', 'You have already signed this contract.');
        }

        $validator = Validator::make($request->all(), [
            // OLD-SYSTEM PARITY (cp2_contracts.inc:61-63): full name (> 4 chars AND a
            // space = firstname + surname), valid email, position 3+ chars.
            'signee_name' => ['required', 'string', 'min:5', 'max:255', 'regex:/\s/'],
            'signee_email' => 'required|email|max:255',
            'signee_position' => 'required|string|min:3|max:255',
            'signature_data' => 'required|string',
        ], [
            'signee_name.required' => 'Please enter your full name.',
            'signee_name.min' => 'Please enter your full name (first and last).',
            'signee_name.regex' => 'Please enter your firstname and surname (with a space).',
            'signee_email.required' => 'Please enter your email address.',
            'signee_email.email' => 'Please enter a valid email address.',
            'signee_position.required' => 'Please enter your position/title.',
            'signee_position.min' => 'Position must be at least 3 characters.',
            'signature_data.required' => 'Please provide your digital signature.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Create signature record
            ContractSignature::create([
                'user_id' => $user->id,
                'contract_id' => $contract->id,
                'signee_name' => $request->signee_name,
                'signee_email' => $request->signee_email,
                'signee_position' => $request->signee_position,
                'signature_data' => $request->signature_data,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'signed_at' => now(),
            ]);

            // OLD-SYSTEM PARITY (ContractManager::setUserAgreed): record the agreement
            // date on useroption and clear any re-sign reason, so the gatekeeper
            // re-opens the dashboard.
            DB::table('useroption')
                ->where('userref', $user->bigid)
                ->update([
                    'agreedcontracts'             => now('Europe/London')->format('Y-m-d'),
                    'agreedcontracts_description' => null,
                ]);

            // Log the signature
            Log::info('Contract signed', [
                'contract_id' => $contract->id,
                'contract_title' => $contract->title,
                'user_id' => $user->id,
                'signee_name' => $request->signee_name,
                'signee_email' => $request->signee_email,
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('customer.contracts.index')
                ->with('success', 'Contract signed successfully! A confirmation email has been sent to ' . $request->signee_email);

        } catch (\Exception $e) {
            Log::error('Contract signing failed', [
                'contract_id' => $contract->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'An error occurred while signing the contract. Please try again.')
                ->withInput();
        }
    }
    
    /**
     * Download contract file
     */
    public function downloadFile($id)
    {
        $user = $this->getCustomerUser();
        
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login to download contracts.');
        }
        
        // Get contract that is active and available to this customer
        $contract = Contract::where('id', $id)
            ->active()
            ->forCustomer($user->id)
            ->first();
        
        if (!$contract) {
            return redirect()->route('customer.contracts.index')
                ->with('error', 'Contract not found or you do not have permission to access it.');
        }
        
        if (!$contract->hasFile()) {
            return redirect()->back()->with('error', 'No file attached to this contract.');
        }

        $filePath = storage_path('app/public/' . $contract->file_path);
        
        if (!file_exists($filePath)) {
            Log::error('Contract file not found', [
                'contract_id' => $contract->id,
                'file_path' => $filePath
            ]);
            return redirect()->back()->with('error', 'Contract file not found.');
        }

        // Log the download
        Log::info('Contract file downloaded', [
            'contract_id' => $contract->id,
            'user_id' => $user->id,
            'file_name' => $contract->file_name
        ]);

        return response()->download($filePath, $contract->file_name);
    }

    /**
     * View contract file inline (for PDF)
     */
    public function viewFile($id)
    {
        $user = $this->getCustomerUser();
        
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login to view contracts.');
        }
        
        // Get contract that is active and available to this customer
        $contract = Contract::where('id', $id)
            ->active()
            ->forCustomer($user->id)
            ->first();
        
        if (!$contract) {
            return redirect()->route('customer.contracts.index')
                ->with('error', 'Contract not found or you do not have permission to access it.');
        }
        
        if (!$contract->hasFile()) {
            return redirect()->back()->with('error', 'No file attached to this contract.');
        }

        $filePath = storage_path('app/public/' . $contract->file_path);
        
        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', 'Contract file not found.');
        }

        // Only allow inline viewing for PDFs
        if (strtolower($contract->file_type) !== 'pdf') {
            return $this->downloadFile($id);
        }

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $contract->file_name . '"'
        ]);
    }

    /**
     * Debug method - only for testing, remove in production
     */
    public function debug()
    {
        $user = $this->getCustomerUser();
        
        if (!$user) {
            return response()->json(['error' => 'Not logged in']);
        }
        
        // Get all contracts
        $allContracts = Contract::all();
        
        // Get contracts for this user
        $userContracts = Contract::active()->forCustomer($user->id)->get();
        
        // Check pivot table
        $pivotEntries = DB::table('contract_customer')->get();
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'bigid' => $user->bigid,
                'uname' => $user->uname,
            ],
            'all_contracts' => $allContracts->map(function($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'type' => $c->type,
                    'is_active' => $c->is_active,
                    'customer_id' => $c->customer_id,
                    'customers_count' => $c->customers()->count(),
                ];
            }),
            'user_contracts' => $userContracts->pluck('id'),
            'pivot_entries' => $pivotEntries,
        ]);
    }
}
