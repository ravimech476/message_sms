<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SmsgCampaign;
use App\Models\User;
use App\Services\BulkThroughputService;
use App\Services\WalletValidationService;
use App\Services\Queue\CampaignQueueService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Mobile App Quick Campaign Controller
 * 
 * Handles quick SMS campaign submission from mobile app
 */
class QuickCampaignController extends Controller
{
    protected $bulkThroughputService;
    protected $walletValidationService;
    protected $campaignQueueService;

    public function __construct()
    {
        $this->bulkThroughputService = new BulkThroughputService();
        $this->walletValidationService = new WalletValidationService();

        try {
            $this->campaignQueueService = new CampaignQueueService();
        } catch (\Exception $e) {
            Log::error('Failed to initialize Campaign Queue service: ' . $e->getMessage());
            $this->campaignQueueService = null;
        }
    }

    /**
     * Get sender IDs available for the user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSenderIds(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get available sender IDs from user's keywords
            $senderIds = DB::table('itagg_instance as i')
                ->join('smsshortcodes as s', 'i.smsshortcodes_id', '=', 's.id')
                ->where('i.users_bigid', $user->bigid)
                ->where('i.expiry', '>=', date('Y-m-d'))
                ->where('i.keyword', '*')
                ->where('i.active', 1)
                ->pluck('s.number')
                ->toArray();

            return response()->json([
                'status' => true,
                'message' => 'Sender IDs retrieved successfully',
                'data' => [
                    'sender_ids' => $senderIds,
                    'count' => count($senderIds),
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Get Sender IDs Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve sender IDs',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Submit a quick campaign
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $userref = $user->bigid;

            // Validation
            $errors = [];

            $campaignname = $request->input('campaign_name', '');
            $senderId = $request->input('sender_id', '');
            $otherSenderId = $request->input('other_sender_id', '');
            $recipients = $request->input('recipients', '');
            $message = $request->input('message', '');
            $routeLetter = $request->input('route_letter', '');

            if (empty(trim($campaignname))) {
                $errors[] = 'Campaign name is required.';
            }

            // Sender ID validation
            $finalSenderId = '';
            if (empty($senderId) || $senderId === 'choose') {
                $errors[] = 'Please select a sender ID.';
            } elseif ($senderId === 'useotherbelow') {
                if (empty(trim($otherSenderId))) {
                    $errors[] = 'Please enter a custom sender ID.';
                } else {
                    $finalSenderId = trim($otherSenderId);
                }
            } else {
                $finalSenderId = $senderId;
            }

            if (empty(trim($recipients))) {
                $errors[] = 'At least one recipient is required.';
            }

            if (empty(trim($message))) {
                $errors[] = 'SMS message is required.';
            }

            if (!empty($errors)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ], 422);
            }

            // Parse recipients
            $recipientsArray = preg_split('/[\s,\n\r]+/', trim($recipients));
            $recipientsArray = array_filter($recipientsArray, function ($num) {
                return !empty(trim($num));
            });
            $recipientCount = count($recipientsArray);

            if ($recipientCount == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No valid phone numbers found.'
                ], 422);
            }

            // Check bulk throughput limit
            $throughputCheck = $this->bulkThroughputService->checkAndUpdateThroughput($userref);

            if (!$throughputCheck['allowed']) {
                Log::warning('Campaign SMS blocked due to throughput limit', [
                    'user' => $userref,
                    'campaign' => $campaignname,
                    'limit' => $throughputCheck['limit'] ?? 0
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'You have reached your daily SMS send limit of ' . ($throughputCheck['limit'] ?? 0) . ' messages. Please contact support to increase your limit.'
                ], 429);
            }

            // Check wallet balance
            $walletCheck = $this->walletValidationService->validateWalletBalance($userref, $recipientCount, []);

            if (!$walletCheck['has_funds']) {
                Log::warning('Campaign SMS blocked due to insufficient funds', [
                    'user' => $userref,
                    'campaign' => $campaignname,
                    'balance' => $walletCheck['current_balance'] ?? 0,
                    'required' => $walletCheck['required_amount'] ?? 0
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient wallet funds.',
                    'data' => [
                        'current_balance' => $walletCheck['current_balance'] ?? 0,
                        'required_amount' => $walletCheck['required_amount'] ?? 0,
                    ]
                ], 402);
            }

            // Clean message
            $cleanMessage = trim(str_replace(["\r\n", "\r", "\n"], ' ', $message));
            $from = trim($finalSenderId);

            // Generate unique campaign ID
            $campaignid = SmsgCampaign::generateCampaignId(5);
            $filename = "quick_campaign_{$userref}_{$campaignid}.csv";
            
            // Use storage path for cross-platform compatibility
            $filepath = CampaignQueueService::getCampaignFilePath($filename);

            // Create CSV file for campaign
            $file = fopen($filepath, 'w');
            $lineCount = 0;

            foreach ($recipientsArray as $recipient) {
                $recipient = trim($recipient);
                if (!empty($recipient)) {
                    // Remove non-numeric characters
                    $recipient = preg_replace('/\D/', '', $recipient);
                    if (!empty($recipient)) {
                        // CSV format: mobile, sendtime, originator, message, field1, field2, field3, route
                        $row = [$recipient, '', trim($from), $cleanMessage, '', '', '', $routeLetter];
                        fputcsv($file, $row);
                        $lineCount++;
                    }
                }
            }
            fclose($file);

            // Insert campaign record
            $campaign = SmsgCampaign::create([
                'campaignid' => $campaignid,
                'userref' => $userref,
                'datetime' => date('YmdHis'),
                'campaignname' => $campaignname,
                'filename' => $filename,
                'numlines' => $lineCount,
                'numlinesdone' => 0,
                'status' => 'filewaiting',
                'uniqueid' => SmsgCampaign::generateUniqueId(32)
            ]);

            // Queue campaign for processing via RabbitMQ
            $queueSuccess = false;
            if ($this->campaignQueueService) {
                $queueResult = $this->campaignQueueService->queueCampaign([
                    'campaign_id' => $campaignid,
                    'campaign_row_id' => $campaign->id,
                    'user_ref' => $userref,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'num_lines' => $lineCount,
                    'num_lines_done' => 0,
                    'metadata' => [
                        'campaign_name' => $campaignname,
                        'sender_id' => $from,
                        'route' => $routeLetter,
                        'source' => 'mobile_quick_campaign'
                    ]
                ]);

                if ($queueResult['success']) {
                    Log::info('Mobile Campaign queued to RabbitMQ', [
                        'campaign_id' => $campaignid,
                        'queue_id' => $queueResult['queue_id'] ?? null,
                        'line_count' => $lineCount
                    ]);
                    $queueSuccess = true;
                } else {
                    Log::error('Failed to queue mobile campaign to RabbitMQ', [
                        'campaign_id' => $campaignid,
                        'error' => $queueResult['error'] ?? 'Unknown'
                    ]);

                    // Update campaign status to indicate queue failure
                    SmsgCampaign::where('campaignid', $campaignid)
                        ->update(['status' => 'failed', 'statusinfo' => 'Failed to queue: ' . ($queueResult['error'] ?? 'Unknown')]);
                }
            }

            if ($queueSuccess || !$this->campaignQueueService) {
                return response()->json([
                    'status' => true,
                    'message' => "Campaign \"{$campaignname}\" submitted successfully! {$lineCount} message(s) queued for processing.",
                    'data' => [
                        'campaign_id' => $campaignid,
                        'campaign_name' => $campaignname,
                        'recipient_count' => $lineCount,
                        'sender_id' => $from,
                        'status' => 'filewaiting',
                    ],
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to queue campaign for processing. Please try again.'
                ], 500);
            }

        } catch (\Throwable $ex) {
            Log::error('Mobile Quick Campaign Submit Error: ' . $ex->getMessage());
            Log::error('Mobile Quick Campaign Submit Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to submit campaign',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a bulk campaign file
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadBulkCampaign(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $userref = $user->bigid;

            // Validation
            $errors = [];

            $campaignname = $request->input('campaign_name', '');

            if (empty(trim($campaignname))) {
                $errors[] = 'Campaign name is required.';
            }

            if (!$request->hasFile('file')) {
                $errors[] = 'No file was uploaded.';
            } else {
                $file = $request->file('file');

                if (!$file->isValid()) {
                    $errors[] = 'There was an error uploading your file.';
                } elseif (strtolower($file->getClientOriginalExtension()) !== 'csv') {
                    $errors[] = 'Your file is not a CSV file. Filename extension should be ".csv".';
                } elseif ($file->getSize() > 104857600) { // 100MB
                    $errors[] = 'Your file is too big. 100MB maximum.';
                }
            }

            if (!empty($errors)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ], 422);
            }

            // Generate campaign ID
            $campaignid = SmsgCampaign::generateCampaignId(5);
            $filename = "uploadedcampaign_{$userref}_{$campaignid}.csv";
            
            // Use storage path for cross-platform compatibility
            $filepath = CampaignQueueService::getCampaignFilePath($filename);

            // Move uploaded file
            $file = $request->file('file');
            copy($file->getPathname(), $filepath);

            // Count lines in file
            $handle = fopen($filepath, 'r');
            $lineCount = 0;
            while (($line = fgets($handle)) !== false) {
                if (trim($line) !== '') {
                    $lineCount++;
                }
            }
            fclose($handle);

            if ($lineCount == 0) {
                // Clean up the file
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                return response()->json([
                    'status' => false,
                    'message' => 'The uploaded file is empty or contains no valid data.'
                ], 422);
            }

            // Insert campaign record
            $campaign = SmsgCampaign::create([
                'campaignid' => $campaignid,
                'userref' => $userref,
                'datetime' => date('YmdHis'),
                'campaignname' => $campaignname,
                'filename' => $filename,
                'numlines' => $lineCount,
                'numlinesdone' => 0,
                'status' => 'filewaiting',
                'uniqueid' => SmsgCampaign::generateUniqueId(32)
            ]);

            // Queue campaign for processing via RabbitMQ
            $queueSuccess = false;
            if ($this->campaignQueueService) {
                $queueResult = $this->campaignQueueService->queueCampaign([
                    'campaign_id' => $campaignid,
                    'campaign_row_id' => $campaign->id,
                    'user_ref' => $userref,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'num_lines' => $lineCount,
                    'num_lines_done' => 0,
                    'metadata' => [
                        'campaign_name' => $campaignname,
                        'source' => 'mobile_bulk_upload'
                    ]
                ]);

                if ($queueResult['success']) {
                    Log::info('Mobile Bulk Campaign queued to RabbitMQ', [
                        'campaign_id' => $campaignid,
                        'queue_id' => $queueResult['queue_id'] ?? null,
                        'line_count' => $lineCount
                    ]);
                    $queueSuccess = true;
                } else {
                    Log::error('Failed to queue mobile bulk campaign to RabbitMQ', [
                        'campaign_id' => $campaignid,
                        'error' => $queueResult['error'] ?? 'Unknown'
                    ]);
                }
            }

            if ($queueSuccess || !$this->campaignQueueService) {
                return response()->json([
                    'status' => true,
                    'message' => "Campaign file \"{$campaignname}\" uploaded successfully! {$lineCount} line(s) queued for processing.",
                    'data' => [
                        'campaign_id' => $campaignid,
                        'campaign_name' => $campaignname,
                        'line_count' => $lineCount,
                        'original_filename' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'status' => 'filewaiting',
                    ],
                ], 200);
            } else {
                // Campaign will be processed by existing daemon as fallback
                return response()->json([
                    'status' => true,
                    'message' => "Campaign file \"{$campaignname}\" uploaded successfully! System will process the file shortly.",
                    'data' => [
                        'campaign_id' => $campaignid,
                        'campaign_name' => $campaignname,
                        'line_count' => $lineCount,
                        'status' => 'filewaiting',
                    ],
                ], 200);
            }

        } catch (\Throwable $ex) {
            Log::error('Mobile Bulk Campaign Upload Error: ' . $ex->getMessage());
            Log::error('Mobile Bulk Campaign Upload Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to upload campaign file',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Download sample CSV file
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadSampleCsv(Request $request)
    {
        try {
            // Create sample CSV content
            $csvContent = "mobile,custom1,originator,message,sendtime,dlr_url,custom2,route\n";
            $csvContent .= "447123456789,John,YourBrand,Hello {custom1} this is a test message!,,,\n";
            $csvContent .= "447987654321,Jane,YourBrand,Special offer just for you!,,,\n";
            $csvContent .= "447555123456,Customer,YourBrand,Your order has been shipped.,,,d\n";

            return response()->json([
                'status' => true,
                'message' => 'Sample CSV generated',
                'data' => [
                    'filename' => 'sample_campaign.csv',
                    'content' => base64_encode($csvContent),
                    'mime_type' => 'text/csv',
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Sample CSV Download Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to generate sample CSV'
            ], 500);
        }
    }

    /**
     * Get CSV format guide
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCsvFormatGuide(Request $request)
    {
        $guide = [
            [
                'column' => 1,
                'name' => 'Mobile',
                'description' => 'Recipient mobile number (447... format)',
                'required' => true,
                'example' => '447123456789',
            ],
            [
                'column' => 2,
                'name' => 'Custom1',
                'description' => 'Custom field for personalization',
                'required' => false,
                'example' => 'John',
            ],
            [
                'column' => 3,
                'name' => 'Originator',
                'description' => 'Sender ID (who the SMS comes from)',
                'required' => true,
                'example' => 'YourBrand',
            ],
            [
                'column' => 4,
                'name' => 'Message',
                'description' => 'SMS text content',
                'required' => true,
                'example' => 'Hello, this is your message!',
            ],
            [
                'column' => 5,
                'name' => 'Send Time',
                'description' => 'Scheduled send time (YYYYMMDDHHmm)',
                'required' => false,
                'example' => '202412251400',
            ],
            [
                'column' => 6,
                'name' => 'DLR URL',
                'description' => 'Delivery receipt callback URL',
                'required' => false,
                'example' => 'https://yoursite.com/dlr',
            ],
            [
                'column' => 7,
                'name' => 'Custom2',
                'description' => 'Additional custom field',
                'required' => false,
                'example' => '-',
            ],
            [
                'column' => 8,
                'name' => 'Route',
                'description' => 'Route letter (d, p, e, etc.)',
                'required' => false,
                'example' => 'd',
            ],
        ];

        return response()->json([
            'status' => true,
            'message' => 'CSV format guide retrieved',
            'data' => $guide,
        ], 200);
    }

    /**
     * Get campaign history for user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $perPage = $request->input('per_page', 20);
            $page = $request->input('page', 1);

            $campaigns = SmsgCampaign::forUser($user->bigid)
                ->notDeleted()
                ->orderBy('datetime', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $campaignData = $campaigns->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'campaign_id' => $campaign->campaignid,
                    'campaign_name' => $campaign->campaignname,
                    'status' => $campaign->status,
                    'status_info' => $campaign->statusinfo,
                    'num_lines' => $campaign->numlines,
                    'num_lines_done' => $campaign->numlinesdone,
                    'progress_percent' => $campaign->numlines > 0 
                        ? round(($campaign->numlinesdone / $campaign->numlines) * 100, 1) 
                        : 0,
                    'datetime' => Carbon::createFromFormat('YmdHis', $campaign->datetime)->format('d M Y, H:i'),
                    'datetime_raw' => $campaign->datetime,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Campaign history retrieved successfully',
                'data' => [
                    'campaigns' => $campaignData,
                    'pagination' => [
                        'current_page' => $campaigns->currentPage(),
                        'last_page' => $campaigns->lastPage(),
                        'per_page' => $campaigns->perPage(),
                        'total' => $campaigns->total(),
                    ],
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Campaign History Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve campaign history',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Get single campaign details
     * 
     * @param Request $request
     * @param string $campaignId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $campaignId)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $campaign = SmsgCampaign::where('userref', $user->bigid)
                ->where('campaignid', $campaignId)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Campaign retrieved successfully',
                'data' => [
                    'id' => $campaign->id,
                    'campaign_id' => $campaign->campaignid,
                    'campaign_name' => $campaign->campaignname,
                    'status' => $campaign->status,
                    'status_info' => $campaign->statusinfo,
                    'num_lines' => $campaign->numlines,
                    'num_lines_done' => $campaign->numlinesdone,
                    'progress_percent' => $campaign->numlines > 0 
                        ? round(($campaign->numlinesdone / $campaign->numlines) * 100, 1) 
                        : 0,
                    'datetime' => Carbon::createFromFormat('YmdHis', $campaign->datetime)->format('d M Y, H:i'),
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Campaign Show Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve campaign',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Pause a campaign
     * 
     * @param Request $request
     * @param string $campaignId
     * @return \Illuminate\Http\JsonResponse
     */
    public function pause(Request $request, $campaignId)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $campaign = SmsgCampaign::where('userref', $user->bigid)
                ->where('campaignid', $campaignId)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Update campaign status
            $campaign->update([
                'statustmp' => $campaign->status,
                'status' => 'paused',
                'statusinfo' => ($campaign->statusinfo ?? '') . '. ' . date('jS M Y, g:i:sa') . ' pausing (mobile)...'
            ]);

            Log::info('Mobile Campaign Paused', [
                'user_id' => $user->id,
                'campaign_id' => $campaignId,
            ]);

            return response()->json([
                'status' => true,
                'message' => "Campaign '{$campaignId}' has been paused.",
                'data' => [
                    'campaign_id' => $campaignId,
                    'status' => 'paused',
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Campaign Pause Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to pause campaign',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Resume a paused campaign
     * 
     * @param Request $request
     * @param string $campaignId
     * @return \Illuminate\Http\JsonResponse
     */
    public function resume(Request $request, $campaignId)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $campaign = SmsgCampaign::where('userref', $user->bigid)
                ->where('campaignid', $campaignId)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Update campaign status
            $campaign->update([
                'status' => $campaign->statustmp ?? 'completed',
                'statustmp' => '',
                'statusinfo' => ($campaign->statusinfo ?? '') . '. ' . date('jS M Y, g:i:sa') . ' unpausing (mobile)...'
            ]);

            Log::info('Mobile Campaign Resumed', [
                'user_id' => $user->id,
                'campaign_id' => $campaignId,
            ]);

            return response()->json([
                'status' => true,
                'message' => "Campaign '{$campaignId}' has been resumed.",
                'data' => [
                    'campaign_id' => $campaignId,
                    'status' => $campaign->status,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Campaign Resume Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to resume campaign',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a campaign
     * 
     * @param Request $request
     * @param string $campaignId
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request, $campaignId)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $campaign = SmsgCampaign::where('userref', $user->bigid)
                ->where('campaignid', $campaignId)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Update campaign status to deleted
            $campaign->update([
                'status' => 'deleted',
                'statusinfo' => ($campaign->statusinfo ?? '') . '. ' . date('jS M Y, g:i:sa') . ' deleting (mobile)...'
            ]);

            Log::info('Mobile Campaign Deleted', [
                'user_id' => $user->id,
                'campaign_id' => $campaignId,
            ]);

            return response()->json([
                'status' => true,
                'message' => "Campaign '{$campaignId}' has been deleted.",
                'data' => [
                    'campaign_id' => $campaignId,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Campaign Delete Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete campaign',
                'error' => $ex->getMessage()
            ], 500);
        }
    }
}
