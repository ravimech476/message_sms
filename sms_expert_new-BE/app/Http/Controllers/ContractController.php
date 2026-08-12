<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserOption;
use App\Models\UserMargin;
use App\Models\Contract;
use App\Models\ContractSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use App\Services\Queue\EmailQueueService;
use Carbon\Carbon;

class ContractController extends Controller
{
    /**
     * Display the contracts page
     */
    public function index(Request $request)
    {
        $userInfo = session('user_info');
        $userBigid = $userInfo['bigid'] ?? null;

        if (!$userBigid) {
            return redirect()->route('login')->with('error', 'Please login to view contracts.');
        }

        // Get user details
        $user = User::where('bigid', $userBigid)->first();
        
        if (!$user) {
            return redirect()->route('dashboard')->with('error', 'User not found.');
        }

        // Get user option for contract agreement status
        $userOption = UserOption::where('userref', $userBigid)->first();
        
        // Check if user has agreed to contracts
        $hasAgreed = $this->hasUserAgreed($userBigid);
        
        // Get reasons for signing/re-signing
        $reasons = $this->getReasons($userBigid);
        
        // Get date user agreed
        $dateAgreed = $this->dateAgreed($userBigid);
        
        // Get contracts from database for this user
        $mainContracts = Contract::main()
            ->active()
            ->forCustomer($user->id)
            ->get()
            ->map(function($contract) use ($user) {
                $contract->is_signed = $contract->isSignedByUser($user->id);
                $contract->signature = $contract->getUserSignature($user->id);
                return $contract;
            });
        
        $addendums = Contract::addendum()
            ->active()
            ->forCustomer($user->id)
            ->get()
            ->map(function($contract) use ($user) {
                $contract->is_signed = $contract->isSignedByUser($user->id);
                $contract->signature = $contract->getUserSignature($user->id);
                return $contract;
            });

        // Get privacy policies from database for this user
        $privacyPolicies = Contract::privacyPolicy()
            ->active()
            ->forCustomer($user->id)
            ->get()
            ->map(function($contract) use ($user) {
                $contract->is_signed = $contract->isSignedByUser($user->id);
                $contract->signature = $contract->getUserSignature($user->id);
                return $contract;
            });

        // Log for debugging
        Log::info('Contracts loaded for user', [
            'user_id' => $user->id,
            'bigid' => $userBigid,
            'main_contracts_count' => $mainContracts->count(),
            'addendums_count' => $addendums->count(),
            'privacy_policies_count' => $privacyPolicies->count()
        ]);

        // Format frozen date
        $dateFrozenStr = null;
        if ($user->datefrozen) {
            $dateFrozenStr = Carbon::createFromTimestamp($user->datefrozen)->format('jS F, Y');
        }

        // Get pricing effective date from user_margin table
        $userMargin = UserMargin::where('user_id', $user->id)->first();
        $pricingEffectiveDate = null;
        $pricingEffectiveDateShort = null;
        
        if ($userMargin && $userMargin->updated_at) {
            $pricingEffectiveDate = Carbon::parse($userMargin->updated_at)->format('F j, Y'); // February 1, 2024
            $pricingEffectiveDateShort = Carbon::parse($userMargin->updated_at)->format('d/m/Y'); // 01/02/2024
        } else {
            // Default fallback date
            $pricingEffectiveDate = 'February 1, 2024';
            $pricingEffectiveDateShort = '01/02/2024';
        }

        return view('contracts.index', compact(
            'user',
            'hasAgreed',
            'reasons',
            'dateAgreed',
            'mainContracts',
            'addendums',
            'privacyPolicies',
            'dateFrozenStr',
            'pricingEffectiveDate',
            'pricingEffectiveDateShort'
        ));
    }

    /**
     * Show a specific contract
     */
    public function show($id)
    {
        $userInfo = session('user_info');
        $userBigid = $userInfo['bigid'] ?? null;

        if (!$userBigid) {
            return redirect()->route('login')->with('error', 'Please login to view contracts.');
        }

        $user = User::where('bigid', $userBigid)->first();
        
        if (!$user) {
            return redirect()->route('dashboard')->with('error', 'User not found.');
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
            return redirect()->route('contracts.index')
                ->with('error', 'Contract not found or you do not have permission to view it.');
        }
        
        $isSigned = $contract->isSignedByUser($user->id);
        $signature = $contract->getUserSignature($user->id);

        return view('contracts.show', compact('contract', 'isSigned', 'signature', 'user'));
    }

    /**
     * Sign a contract
     */
    public function sign(Request $request, $id)
    {
        $userInfo = session('user_info');
        $userBigid = $userInfo['bigid'] ?? null;

        if (!$userBigid) {
            return redirect()->route('login')->with('error', 'Please login to sign contracts.');
        }

        $user = User::where('bigid', $userBigid)->first();
        
        if (!$user) {
            return redirect()->route('contracts.index')->with('error', 'User not found.');
        }

        // Get contract that is active and available to this customer
        $contract = Contract::where('id', $id)
            ->active()
            ->forCustomer($user->id)
            ->first();

        if (!$contract) {
            return redirect()->route('contracts.index')
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

        $request->validate([
            'signee_name' => 'required|string|min:3|max:255',
            'signee_email' => 'required|email|max:255',
            'signee_position' => 'required|string|min:2|max:255',
            'signature_data' => 'required|string',
        ], [
            'signee_name.required' => 'Please enter your full name.',
            'signee_name.min' => 'Name must be at least 3 characters.',
            'signee_email.required' => 'Please enter your email address.',
            'signee_email.email' => 'Please enter a valid email address.',
            'signee_position.required' => 'Please enter your position/title.',
            'signee_position.min' => 'Position must be at least 2 characters.',
            'signature_data.required' => 'Please provide your digital signature.',
        ]);

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

            // Send confirmation email
            $this->sendContractSignatureEmail(
                $user,
                $contract,
                $request->signee_name,
                $request->signee_email,
                $request->signee_position
            );

            // Log the signature
            Log::info('Contract signed', [
                'contract_id' => $contract->id,
                'contract_title' => $contract->title,
                'user_id' => $user->id,
                'signee_name' => $request->signee_name,
                'signee_email' => $request->signee_email,
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('contracts.index')
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
        $userInfo = session('user_info');
        $userBigid = $userInfo['bigid'] ?? null;

        if (!$userBigid) {
            return redirect()->route('login')->with('error', 'Please login to download contracts.');
        }

        $user = User::where('bigid', $userBigid)->first();
        
        if (!$user) {
            return redirect()->route('contracts.index')->with('error', 'User not found.');
        }

        // Get contract that is active and available to this customer
        $contract = Contract::where('id', $id)
            ->active()
            ->forCustomer($user->id)
            ->first();
        
        if (!$contract) {
            return redirect()->route('contracts.index')
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
        $userInfo = session('user_info');
        $userBigid = $userInfo['bigid'] ?? null;

        if (!$userBigid) {
            return redirect()->route('login')->with('error', 'Please login to view contracts.');
        }

        $user = User::where('bigid', $userBigid)->first();
        
        if (!$user) {
            return redirect()->route('contracts.index')->with('error', 'User not found.');
        }

        // Get contract that is active and available to this customer
        $contract = Contract::where('id', $id)
            ->active()
            ->forCustomer($user->id)
            ->first();
        
        if (!$contract) {
            return redirect()->route('contracts.index')
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
     * Process contract agreement (legacy method - kept for backward compatibility)
     */
    public function agree(Request $request)
    {
        $request->validate([
            'signeename' => 'required|string|min:5|regex:/\s/',
            'signeeemail' => 'required|email',
            'signeeposition' => 'required|string|min:3',
        ], [
            'signeename.required' => 'Please enter your full name.',
            'signeename.min' => 'Please enter your firstname and surname.',
            'signeename.regex' => 'Please enter your firstname and surname (separated by space).',
            'signeeemail.required' => 'Please enter your email address.',
            'signeeemail.email' => 'Please enter a valid email address.',
            'signeeposition.required' => 'Please enter your position.',
            'signeeposition.min' => 'Position must be at least 3 characters.',
        ]);

        $userInfo = session('user_info');
        $userBigid = $userInfo['bigid'] ?? null;

        if (!$userBigid) {
            return redirect()->route('login')->with('error', 'Please login to agree to contracts.');
        }

        // Get user details
        $user = User::where('bigid', $userBigid)->first();

        if (!$user) {
            return redirect()->route('contracts.index')->with('error', 'User not found.');
        }

        $signeeName = $request->input('signeename');
        $signeeEmail = $request->input('signeeemail');
        $signeePosition = $request->input('signeeposition');
        $signeeReason = $request->input('signeereason', '');

        try {
            // Send confirmation email
            $this->sendContractConfirmationEmail(
                $user,
                $signeeName,
                $signeeEmail,
                $signeePosition,
                $signeeReason
            );

            // Update user's agreement status
            $this->setUserAgreed($userBigid);

            return redirect()->route('dashboard')->with('success', 'Thank you for agreeing to the contract. A confirmation email has been sent.');

        } catch (\Exception $e) {
            Log::error('Contract agreement error: ' . $e->getMessage());
            return redirect()->route('contracts.index')->with('error', 'An error occurred. Please try again.');
        }
    }

    /**
     * Check if user has agreed to contracts
     */
    private function hasUserAgreed($userBigid)
    {
        $userOption = UserOption::where('userref', $userBigid)->first();
        
        if (!$userOption) {
            return true; // If no record, assume agreed
        }
        
        return !is_null($userOption->agreedcontracts);
    }

    /**
     * Get date user agreed to contracts
     */
    private function dateAgreed($userBigid)
    {
        $userOption = UserOption::where('userref', $userBigid)->first();
        
        if ($userOption && $userOption->agreedcontracts) {
            return Carbon::parse($userOption->agreedcontracts)->format('F j, Y');
        }
        
        return null;
    }

    /**
     * Get reasons for contract signing/re-signing
     */
    private function getReasons($userBigid)
    {
        $userOption = UserOption::where('userref', $userBigid)->first();
        
        if ($userOption) {
            return $userOption->agreedcontracts_description;
        }
        
        return null;
    }

    /**
     * Set user as agreed to contracts
     */
    private function setUserAgreed($userBigid)
    {
        UserOption::updateOrCreate(
            ['userref' => $userBigid],
            [
                'agreedcontracts' => Carbon::now()->format('Y-m-d'),
                'agreedcontracts_description' => null
            ]
        );
    }

    /**
     * Send contract signature confirmation email
     */
    private function sendContractSignatureEmail($user, $contract, $signeeName, $signeeEmail, $signeePosition)
    {
        $contactEmail = $user->contactemail;
        $accountName = $user->uname;

        // Determine CC recipients
        $adminEmail = config('mail.contract_cc_email', env('SUPPORT_EMAIL', 'anand@nedholdings.com'));
        $ccRecipients = [$adminEmail];

        if (strtolower(trim($signeeEmail)) !== strtolower(trim($contactEmail))) {
            $ccRecipients[] = $signeeEmail;
            $msgBit = 'This confirmation has been copied to the signee and the main account holder, as well as to SMS Expert for our internal records.';
        } else {
            $msgBit = 'This confirmation has been copied to SMS Expert for our internal records.';
        }

        $emailContent = "Hi<br><br>" .
            "Many thanks for signing the contract \"<b>{$contract->title}</b>\" electronically (account: <i>{$accountName}</i>).<br><br>" .
            "<b>Contract:</b> {$contract->title}<br>" .
            "<b>Contract Type:</b> " . ucfirst($contract->type) . "<br>" .
            "<b>Signee:</b> {$signeeName}, {$signeePosition}<br>" .
            "<b>Date Signed:</b> " . now()->format('d M Y, h:i A') . "<br><br>" .
            "{$msgBit}<br>";

        try {
            // Send contract signed email via RabbitMQ queue
            $emailQueueService = new EmailQueueService();
            $emailQueueService->queueEmail(
                'App\\Mail\\ContractSignedMail',
                $contactEmail,
                [
                    'to_address' => $contactEmail,
                    'cc_addresses' => $ccRecipients,
                    'contract_title' => $contract->title,
                    'email_content' => $emailContent,
                    'signed_by' => $signeeName,
                    'date_signed' => now()->format('d M Y, h:i A'),
                ],
                $ccRecipients
            );
        } catch (\Exception $e) {
            Log::error('Failed to send contract signature email: ' . $e->getMessage());
        }
    }

    /**
     * Send contract confirmation email (legacy)
     */
    private function sendContractConfirmationEmail($user, $signeeName, $signeeEmail, $signeePosition, $signeeReason)
    {
        $contactEmail = $user->contactemail;
        $accountName = $user->uname;

        // Determine CC recipients
        $adminEmail = config('mail.contract_cc_email', env('SUPPORT_EMAIL', 'anand@nedholdings.com'));
        $ccRecipients = [$adminEmail];

        if (strtolower(trim($signeeEmail)) !== strtolower(trim($contactEmail))) {
            $ccRecipients[] = $signeeEmail;
            $msgBit = 'This confirmation has been copied to the signee and the main account holder, as well as to SMS Expert for our internal records.';
        } else {
            $msgBit = 'This confirmation has been copied to SMS Expert for our internal records.';
        }

        $emailContent = "Hi<br><br>" .
            "Many thanks for agreeing electronically to the SMS Expert contract (account: <i>{$accountName}</i>).<br><br>" .
            "<b>Signee:</b> {$signeeName}, {$signeePosition}<br><br>" .
            "<b>Reason for latest signing:</b> " . urldecode($signeeReason) . "<br><br>" .
            "{$msgBit}<br>";

        try {
            // Send contract agreement email via RabbitMQ queue
            $emailQueueService = new EmailQueueService();
            $emailQueueService->queueEmail(
                'App\\Mail\\ContractAgreementMail',
                $contactEmail,
                [
                    'to_address' => $contactEmail,
                    'cc_addresses' => $ccRecipients,
                    'email_content' => $emailContent,
                ],
                $ccRecipients
            );
        } catch (\Exception $e) {
            Log::error('Failed to send contract confirmation email: ' . $e->getMessage());
        }
    }
}
