<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Contract;
use App\Models\ContractSignature;

/**
 * Mobile App Contracts Controller
 * 
 * Handles contracts and terms for the mobile application
 */
class ContractController extends Controller
{
    /**
     * Get all contracts for the authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $userId = $user->id;

            // Get contracts for this customer
            $mainContracts = Contract::active()
                ->forCustomer($userId)
                ->main()
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function($contract) use ($userId) {
                    return $this->formatContract($contract, $userId);
                });

            $addendums = Contract::active()
                ->forCustomer($userId)
                ->addendum()
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function($contract) use ($userId) {
                    return $this->formatContract($contract, $userId);
                });

            $privacyPolicies = Contract::active()
                ->forCustomer($userId)
                ->privacyPolicy()
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function($contract) use ($userId) {
                    return $this->formatContract($contract, $userId);
                });

            // Calculate statistics
            $allContracts = Contract::active()->forCustomer($userId)->get();
            $signedCount = 0;
            $pendingCount = 0;

            foreach ($allContracts as $contract) {
                if ($contract->requires_signature) {
                    if ($contract->isSignedByUser($userId)) {
                        $signedCount++;
                    } else {
                        $pendingCount++;
                    }
                }
            }

            // Check if user has agreed to contracts (from useroption table)
            $userOption = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->first();

            $hasAgreed = false;
            $agreedDate = null;

            if ($userOption && isset($userOption->agreedcontracts) && $userOption->agreedcontracts) {
                $hasAgreed = true;
                // Try to get the agreed date if stored
                if (isset($userOption->agreedcontracts_date)) {
                    $agreedDate = date('F j, Y', strtotime($userOption->agreedcontracts_date));
                }
            }

            // Get pricing info (static for now, could be from database)
            $pricing = $this->getPricingInfo();

            return response()->json([
                'status' => true,
                'message' => 'Contracts retrieved successfully',
                'data' => [
                    'statistics' => [
                        'master_agreements' => $mainContracts->count(),
                        'addendums' => $addendums->count(),
                        'privacy_policies' => $privacyPolicies->count(),
                        'signed' => $signedCount,
                        'pending' => $pendingCount,
                    ],
                    'main_contracts' => $mainContracts,
                    'addendums' => $addendums,
                    'privacy_policies' => $privacyPolicies,
                    'pricing' => $pricing,
                    'agreement_status' => [
                        'has_agreed' => $hasAgreed,
                        'agreed_date' => $agreedDate,
                    ],
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Contracts Error: ' . $ex->getMessage());
            Log::error('Mobile Contracts Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load contracts',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single contract details
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userId = $user->id;

            $contract = Contract::active()
                ->forCustomer($userId)
                ->findOrFail($id);

            $formattedContract = $this->formatContract($contract, $userId);
            $formattedContract['content'] = $contract->content;

            return response()->json([
                'status' => true,
                'message' => 'Contract retrieved successfully',
                'data' => $formattedContract,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Contract not found',
            ], 404);
        } catch (\Throwable $ex) {
            Log::error('Mobile Contract Show Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load contract',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Sign a contract with hand signature
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function sign(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userId = $user->id;

            $contract = Contract::active()
                ->forCustomer($userId)
                ->where('requires_signature', true)
                ->findOrFail($id);

            // Check if already signed
            if ($contract->isSignedByUser($userId)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contract already signed',
                ], 422);
            }

            // Get signature data (base64 encoded image)
            $signatureData = $request->input('signature');
            $signaturePath = null;

            // Save signature image if provided
            if ($signatureData) {
                try {
                    // Remove data URL prefix if present
                    $signatureData = preg_replace('/^data:image\/\w+;base64,/', '', $signatureData);
                    $signatureImage = base64_decode($signatureData);

                    if ($signatureImage) {
                        // Generate unique filename
                        $filename = 'signatures/contract_' . $id . '_user_' . $userId . '_' . time() . '.png';
                        
                        // Store the signature image
                        Storage::disk('public')->put($filename, $signatureImage);
                        $signaturePath = $filename;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to save signature image: ' . $e->getMessage());
                    // Continue without signature image - not critical
                }
            }

            // Create signature record
            $signatureRecord = ContractSignature::create([
                'contract_id' => $contract->id,
                'user_id' => $userId,
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'signature_image' => $signaturePath,
                'signed_via' => 'mobile_app',
            ]);

            // Log the signature event
            Log::info('Contract signed via mobile app', [
                'contract_id' => $contract->id,
                'user_id' => $userId,
                'signature_id' => $signatureRecord->id,
                'has_signature_image' => !empty($signaturePath),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Contract signed successfully',
                'data' => [
                    'signature_id' => $signatureRecord->id,
                    'signed_at' => $signatureRecord->signed_at->format('F j, Y'),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Contract not found or does not require signature',
            ], 404);
        } catch (\Throwable $ex) {
            Log::error('Mobile Contract Sign Error: ' . $ex->getMessage());
            Log::error('Mobile Contract Sign Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to sign contract',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Get download URL for a contract
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function download(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userId = $user->id;

            $contract = Contract::active()
                ->forCustomer($userId)
                ->findOrFail($id);

            if (!$contract->hasFile()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No file available for download',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Download URL retrieved successfully',
                'data' => [
                    'url' => $contract->getFileUrl(),
                    'file_name' => $contract->file_name,
                    'file_type' => $contract->file_type,
                    'file_size' => $contract->getFileSizeFormatted(),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Contract not found',
            ], 404);
        } catch (\Throwable $ex) {
            Log::error('Mobile Contract Download Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to get download URL',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's signature for a contract
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSignature(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userId = $user->id;

            $contract = Contract::active()
                ->forCustomer($userId)
                ->findOrFail($id);

            $signature = $contract->getUserSignature($userId);

            if (!$signature) {
                return response()->json([
                    'status' => false,
                    'message' => 'No signature found for this contract',
                ], 404);
            }

            $signatureImageUrl = null;
            if ($signature->signature_image) {
                $signatureImageUrl = asset('storage/' . $signature->signature_image);
            }

            return response()->json([
                'status' => true,
                'message' => 'Signature retrieved successfully',
                'data' => [
                    'signature_id' => $signature->id,
                    'signed_at' => $signature->signed_at ? date('F j, Y \a\t g:i A', strtotime($signature->signed_at)) : null,
                    'signed_via' => $signature->signed_via ?? 'web',
                    'signature_image_url' => $signatureImageUrl,
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Contract not found',
            ], 404);
        } catch (\Throwable $ex) {
            Log::error('Mobile Get Signature Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to get signature',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Format contract for API response
     */
    private function formatContract($contract, $userId)
    {
        $signature = $contract->getUserSignature($userId);
        $signatureStatus = 'none';
        $signedAt = null;

        if ($contract->requires_signature) {
            if ($signature) {
                $signatureStatus = 'signed';
                $signedAt = $signature->signed_at ? date('F j, Y', strtotime($signature->signed_at)) : null;
            } else {
                $signatureStatus = 'pending';
            }
        }

        return [
            'id' => $contract->id,
            'title' => $contract->title,
            'type' => $contract->type,
            'version' => $contract->version,
            'updated_at' => $contract->updated_at ? date('d M Y', strtotime($contract->updated_at)) : null,
            'has_file' => $contract->hasFile(),
            'file_name' => $contract->file_name,
            'file_type' => strtoupper($contract->file_type ?? 'PDF'),
            'file_size' => $contract->getFileSizeFormatted(),
            'requires_signature' => $contract->requires_signature,
            'signature_status' => $signatureStatus,
            'signed_at' => $signedAt,
        ];
    }

    /**
     * Get pricing information
     */
    private function getPricingInfo()
    {
        return [
            'effective_date' => 'November 29, 2025',
            'items' => [
                [
                    'title' => 'Virtual UK Mobile Numbers / Keywords on 60300',
                    'description' => 'Annual subscription fee',
                    'price' => '£250/year',
                    'icon' => 'phone',
                ],
                [
                    'title' => 'SMS to Overseas Mobiles',
                    'description' => 'All sent volumes',
                    'price' => '£0.065',
                    'icon' => 'public',
                ],
                [
                    'title' => 'SMS to UK Mobiles',
                    'description' => 'Based on monthly sent volumes',
                    'icon' => 'flag',
                    'tiers' => [
                        ['range' => 'Up to 20,000 messages', 'price' => '£0.0377'],
                        ['range' => 'Up to 50,000 messages', 'price' => '£0.0319'],
                        ['range' => 'Over 50,000 messages', 'price' => '£0.0290'],
                    ],
                ],
            ],
        ];
    }
}
