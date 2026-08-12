<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Traits\LegacyCustomerList;
use App\Models\Contract;
use App\Models\ContractSignature;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ContractController extends Controller
{
    use LegacyCustomerList;

    /**
     * Display a listing of contracts
     */
    public function index()
    {
        $mainContracts = Contract::with(['customer', 'customers', 'signatures'])->main()->latest()->get();
        $addendums = Contract::with(['customer', 'customers', 'signatures'])->addendum()->latest()->get();
        $privacyPolicies = Contract::with(['customer', 'customers', 'signatures'])->privacyPolicy()->latest()->get();
        
        // Debug log
        \Log::info('Admin Contracts Index', [
            'main_count' => $mainContracts->count(),
            'addendums_count' => $addendums->count(),
            'privacy_policies_count' => $privacyPolicies->count(),
            'all_contracts' => Contract::select('id', 'title', 'type', 'is_active')->get()->toArray()
        ]);
        
        return view('admin.contracts.index', compact('mainContracts', 'addendums', 'privacyPolicies'));
    }

    /**
     * Show the form for creating a new contract
     */
    public function create()
    {
        $customers = $this->getLegacyCustomers()
            ->map(fn ($user) => $this->decorateContractCustomer($user))
            ->filter(fn ($user) => !empty($user->display_name) && !empty($user->display_email))
            ->values();

        return view('admin.contracts.create', compact('customers'));
    }

    /**
     * Decode URL-encoded customer fields and add display_name / display_email.
     */
    private function decorateContractCustomer($user)
    {
        $decode = fn ($v) => $v ? str_replace('+', ' ', urldecode($v)) : null;
        $contactname = $decode($user->contactname ?? null);
        $busname = $decode($user->busname ?? null);
        $uname = $decode($user->uname ?? null);
        $contactemail = $user->contactemail ? urldecode($user->contactemail) : null;

        $user->display_name = $contactname ?: ($busname ?: $uname);
        $user->display_email = $contactemail;
        return $user;
    }

    /**
     * Store a newly created contract
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'contract_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            'type' => 'required|in:main,addendum,privacy_policy',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:users,id',
            'is_active' => 'boolean',
            'requires_signature' => 'boolean',
        ], [
            'contract_file.mimes' => 'Contract file must be a PDF, DOC, or DOCX file.',
            'contract_file.max' => 'Contract file must not exceed 10MB.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check that either content or file is provided
        if (empty($request->content) && !$request->hasFile('contract_file')) {
            return redirect()->back()
                ->withErrors(['content' => 'Please provide either contract content or upload a contract file.'])
                ->withInput();
        }

        // Handle file upload
        $filePath = null;
        $fileName = null;
        $fileSize = null;
        $fileType = null;

        if ($request->hasFile('contract_file')) {
            $file = $request->file('contract_file');
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileType = $file->getClientOriginalExtension();
            
            // Generate unique filename
            $uniqueName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
            
            // Store file in storage/app/public/contracts
            $filePath = $file->storeAs('contracts', $uniqueName, 'public');
            
            Log::info('Contract file uploaded', [
                'original_name' => $fileName,
                'stored_path' => $filePath,
                'size' => $fileSize,
                'type' => $fileType
            ]);
        }

        // Create contract
        $contract = Contract::create([
            'customer_id' => null, // Legacy field, kept for backward compatibility
            'title' => $request->title,
            'content' => $request->content,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'type' => $request->type,
            'is_active' => $request->has('is_active'),
            'requires_signature' => $request->has('requires_signature'),
            'version' => 1,
        ]);

        // Debug log - check what was saved
        Log::info('Contract created', [
            'contract_id' => $contract->id,
            'title' => $contract->title,
            'type' => $contract->type,
            'is_active' => $contract->is_active,
            'request_type' => $request->type,
            'request_is_active' => $request->has('is_active'),
        ]);

        // Attach selected customers (if any)
        if ($request->has('customer_ids') && is_array($request->customer_ids) && count($request->customer_ids) > 0) {
            // Filter out 'all' option if present
            $customerIds = array_filter($request->customer_ids, function($id) {
                return $id !== 'all' && is_numeric($id);
            });
            
            if (!empty($customerIds)) {
                $contract->customers()->attach($customerIds);
            }
        }
        // If no customers selected or 'all' selected, contract is for all customers (no pivot entries)

        return redirect()->route('admin.contracts.index')
            ->with('success', 'Contract created successfully!');
    }

    /**
     * Show the form for editing a contract
     */
    public function edit($id)
    {
        $contract = Contract::with(['customer', 'customers', 'signatures'])->findOrFail($id);

        $customers = $this->getLegacyCustomers()
            ->map(fn ($user) => $this->decorateContractCustomer($user))
            ->filter(fn ($user) => !empty($user->display_name) && !empty($user->display_email))
            ->values();

        return view('admin.contracts.edit', compact('contract', 'customers'));
    }

    /**
     * Update the specified contract
     */
    public function update(Request $request, $id)
    {
        $contract = Contract::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'contract_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            'type' => 'required|in:main,addendum,privacy_policy',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:users,id',
            'is_active' => 'boolean',
            'requires_signature' => 'boolean',
        ], [
            'contract_file.mimes' => 'Contract file must be a PDF, DOC, or DOCX file.',
            'contract_file.max' => 'Contract file must not exceed 10MB.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check that either content or file is provided (unless keeping existing file)
        $keepExistingFile = !$request->has('remove_file') && $contract->hasFile();
        if (empty($request->content) && !$request->hasFile('contract_file') && !$keepExistingFile) {
            return redirect()->back()
                ->withErrors(['content' => 'Please provide either contract content or upload a contract file.'])
                ->withInput();
        }

        // Handle file removal
        if ($request->has('remove_file') && $contract->hasFile()) {
            Storage::disk('public')->delete($contract->file_path);
            $contract->file_path = null;
            $contract->file_name = null;
            $contract->file_size = null;
            $contract->file_type = null;
        }

        // Handle file upload
        if ($request->hasFile('contract_file')) {
            // Delete old file if exists
            if ($contract->hasFile()) {
                Storage::disk('public')->delete($contract->file_path);
            }

            $file = $request->file('contract_file');
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileType = $file->getClientOriginalExtension();
            
            // Generate unique filename
            $uniqueName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
            
            // Store file in storage/app/public/contracts
            $filePath = $file->storeAs('contracts', $uniqueName, 'public');

            $contract->file_path = $filePath;
            $contract->file_name = $fileName;
            $contract->file_size = $fileSize;
            $contract->file_type = $fileType;
            
            Log::info('Contract file updated', [
                'contract_id' => $contract->id,
                'original_name' => $fileName,
                'stored_path' => $filePath,
                'size' => $fileSize,
                'type' => $fileType
            ]);
        }

        // Increment version if content changed
        $version = $contract->version;
        if ($contract->content !== $request->content) {
            $version++;
        }

        $contract->update([
            'customer_id' => null, // Legacy field
            'title' => $request->title,
            'content' => $request->content,
            'type' => $request->type,
            'is_active' => $request->has('is_active'),
            'requires_signature' => $request->has('requires_signature'),
            'version' => $version,
        ]);

        // Sync customers (replaces all existing assignments)
        if ($request->has('customer_ids') && is_array($request->customer_ids) && count($request->customer_ids) > 0) {
            // Filter out 'all' option if present
            $customerIds = array_filter($request->customer_ids, function($id) {
                return $id !== 'all' && is_numeric($id);
            });
            
            if (!empty($customerIds)) {
                $contract->customers()->sync($customerIds);
            } else {
                $contract->customers()->detach(); // Remove all assignments (for all customers)
            }
        } else {
            $contract->customers()->detach(); // Remove all assignments (for all customers)
        }

        return redirect()->route('admin.contracts.index')
            ->with('success', 'Contract updated successfully!');
    }

    /**
     * Remove the specified contract
     */
    public function destroy($id)
    {
        $contract = Contract::findOrFail($id);
        
        // Delete uploaded file if exists
        if ($contract->hasFile()) {
            Storage::disk('public')->delete($contract->file_path);
        }
        
        $contract->delete();

        return redirect()->route('admin.contracts.index')
            ->with('success', 'Contract deleted successfully!');
    }

    /**
     * View signatures for a contract
     */
    public function signatures($id)
    {
        $contract = Contract::with(['signatures.user', 'customer', 'customers'])->findOrFail($id);
        $signatures = $contract->signatures()->with('user')->latest()->get();
        
        return view('admin.contracts.signatures', compact('contract', 'signatures'));
    }

    /**
     * Toggle contract active status
     */
    public function toggleStatus($id)
    {
        $contract = Contract::findOrFail($id);
        $contract->is_active = !$contract->is_active;
        $contract->save();

        return redirect()->back()
            ->with('success', 'Contract status updated successfully!');
    }

    /**
     * Download contract file
     */
    public function downloadFile($id)
    {
        $contract = Contract::findOrFail($id);
        
        if (!$contract->hasFile()) {
            return redirect()->back()->with('error', 'No file attached to this contract.');
        }

        $filePath = storage_path('app/public/' . $contract->file_path);
        
        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', 'Contract file not found.');
        }

        return response()->download($filePath, $contract->file_name);
    }
}
