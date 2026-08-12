<?php

namespace App\Services;

use App\Models\UserFcmToken;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Exception;

class PushNotificationService
{
    protected $fcmProjectId;
    protected $serviceAccountPath;
    protected $isEnabled;
    protected $accessToken;

    public function __construct()
    {
        $this->serviceAccountPath = storage_path('app/firebase/firebase-service-account.json');
        $this->isEnabled = env('PUSH_NOTIFICATIONS_ENABLED', true) && file_exists($this->serviceAccountPath);
        
        if ($this->isEnabled) {
            $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);
            $this->fcmProjectId = $serviceAccount['project_id'] ?? null;
        }
    }

    /**
     * Check if push notifications are enabled
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled && !empty($this->fcmProjectId);
    }

    /**
     * Get FCM v1 API URL
     */
    protected function getFcmUrl(): string
    {
        return "https://fcm.googleapis.com/v1/projects/{$this->fcmProjectId}/messages:send";
    }

    /**
     * Get OAuth2 access token for FCM
     */
    protected function getAccessToken(): ?string
    {
        // Cache the token for 55 minutes (tokens last 60 minutes)
        $cacheKey = 'fcm_access_token';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            if (!file_exists($this->serviceAccountPath)) {
                Log::error('Firebase service account file not found', [
                    'path' => $this->serviceAccountPath
                ]);
                return null;
            }

            $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);

            if (!$serviceAccount) {
                Log::error('Invalid Firebase service account JSON');
                return null;
            }

            // Create JWT
            $now = time();
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT',
            ];
            
            $payload = [
                'iss' => $serviceAccount['client_email'],
                'sub' => $serviceAccount['client_email'],
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            ];

            $base64Header = $this->base64UrlEncode(json_encode($header));
            $base64Payload = $this->base64UrlEncode(json_encode($payload));
            $signatureInput = $base64Header . '.' . $base64Payload;

            // Sign with private key
            $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
            if (!$privateKey) {
                Log::error('Failed to load Firebase private key');
                return null;
            }

            openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $base64Signature = $this->base64UrlEncode($signature);

            $jwt = $signatureInput . '.' . $base64Signature;

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $accessToken = $data['access_token'] ?? null;
                
                if ($accessToken) {
                    // Cache for 55 minutes
                    Cache::put($cacheKey, $accessToken, 3300);
                    return $accessToken;
                }
            }

            Log::error('Failed to get FCM access token', [
                'response' => $response->body()
            ]);
            return null;

        } catch (Exception $e) {
            Log::error('Error getting FCM access token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Base64 URL encode
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send push notification to a user
     *
     * @param int $userId User ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $data Additional data
     * @return array Result with status and details
     */
    public function sendToUser(int $userId, string $title, string $message, string $type = 'general', array $data = []): array
    {
        try {
            // Get user's bigid
            $user = DB::table('users')->where('id', $userId)->first();
            $userBigId = $user ? $user->bigid : null;

            // Store notification in database
            $notification = UserNotification::create([
                'user_id' => $userId,
                'user_bigid' => $userBigId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'icon' => UserNotification::getIconForType($type),
                'data' => $data,
                'is_read' => false,
                'push_sent' => false,
                'push_status' => 'pending',
            ]);

            if (!$this->isEnabled()) {
                Log::info('Push notifications disabled, notification stored only', [
                    'user_id' => $userId,
                    'notification_id' => $notification->id,
                ]);
                return [
                    'success' => true,
                    'message' => 'Notification stored (push disabled)',
                    'notification_id' => $notification->id,
                    'push_sent' => false,
                ];
            }

            // Get user's FCM tokens
            $tokens = UserFcmToken::getActiveTokensForUser($userId);

            if (empty($tokens)) {
                Log::info('No FCM tokens found for user', ['user_id' => $userId]);
                $notification->updatePushStatus('no_tokens', 'No active FCM tokens');
                return [
                    'success' => true,
                    'message' => 'Notification stored (no FCM tokens)',
                    'notification_id' => $notification->id,
                    'push_sent' => false,
                ];
            }

            // Send push notification
            $result = $this->sendFcmNotification($tokens, $title, $message, $type, $data, $notification->id);

            // Update notification status
            if ($result['success']) {
                $notification->updatePushStatus('sent');
            } else {
                $notification->updatePushStatus('failed', $result['error'] ?? 'Unknown error');
            }

            return array_merge($result, ['notification_id' => $notification->id]);

        } catch (Exception $e) {
            Log::error('Push notification error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send push notification',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send push notification to a user by bigid
     *
     * @param string $userBigId User BigID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $data Additional data
     * @return array Result with status and details
     */
    public function sendToUserByBigId(string $userBigId, string $title, string $message, string $type = 'general', array $data = []): array
    {
        try {
            // Get user ID from bigid
            $user = DB::table('users')->where('bigid', $userBigId)->first();

            if (!$user) {
                Log::warning('User not found for push notification', ['bigid' => $userBigId]);
                return [
                    'success' => false,
                    'message' => 'User not found',
                ];
            }

            return $this->sendToUser($user->id, $title, $message, $type, $data);

        } catch (Exception $e) {
            Log::error('Push notification error', [
                'user_bigid' => $userBigId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send push notification',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send FCM notification to multiple tokens using HTTP v1 API
     *
     * @param array $tokens FCM tokens
     * @param string $title Notification title
     * @param string $message Notification body
     * @param string $type Notification type
     * @param array $data Additional data
     * @param int|null $notificationId Notification ID for reference
     * @return array Result
     */
    protected function sendFcmNotification(array $tokens, string $title, string $message, string $type, array $data = [], ?int $notificationId = null): array
    {
        $successCount = 0;
        $failCount = 0;
        $failedTokens = [];

        // Get access token
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Failed to get FCM access token',
                'error' => 'Authentication failed',
            ];
        }

        foreach ($tokens as $token) {
            try {
                // FCM v1 API payload format
                $payload = [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $message,
                        ],
                        'data' => array_merge(
                            array_map('strval', $data), // Convert all values to strings
                            [
                                'type' => $type,
                                'notification_id' => (string) $notificationId,
                                'title' => $title,
                                'message' => $message,
                                'timestamp' => now()->toIso8601String(),
                            ]
                        ),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'sound' => 'default',
                                'channel_id' => 'sms_expert_notifications',
                                // Note: click_action removed - React Native Firebase handles this automatically
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                    'badge' => 1,
                                    'content-available' => 1,
                                ],
                            ],
                        ],
                    ],
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])->post($this->getFcmUrl(), $payload);

                if ($response->successful()) {
                    $successCount++;
                    Log::info('FCM v1 notification sent', [
                        'token' => substr($token, 0, 20) . '...',
                        'notification_id' => $notificationId,
                    ]);
                } else {
                    $failCount++;
                    $failedTokens[] = $token;
                    
                    $responseData = $response->json();
                    $errorCode = $responseData['error']['details'][0]['errorCode'] ?? null;
                    
                    // Check if token is invalid and should be deactivated
                    if (in_array($errorCode, ['INVALID_ARGUMENT', 'UNREGISTERED', 'SENDER_ID_MISMATCH'])) {
                        UserFcmToken::where('fcm_token', $token)->update(['is_active' => false]);
                        Log::warning('Deactivated invalid FCM token', [
                            'token' => substr($token, 0, 20) . '...',
                            'error' => $errorCode,
                        ]);
                    }
                    
                    Log::warning('FCM v1 notification failed', [
                        'token' => substr($token, 0, 20) . '...',
                        'status' => $response->status(),
                        'response' => $responseData,
                    ]);
                }

            } catch (Exception $e) {
                $failCount++;
                $failedTokens[] = $token;
                Log::error('FCM v1 request failed', [
                    'token' => substr($token, 0, 20) . '...',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => $successCount > 0,
            'message' => "Sent to {$successCount} devices, failed: {$failCount}",
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'failed_tokens' => $failedTokens,
        ];
    }

    /**
     * Send bulk push notifications to multiple users
     *
     * @param array $userIds Array of user IDs
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $data Additional data
     * @return array Results
     */
    public function sendToUsers(array $userIds, string $title, string $message, string $type = 'general', array $data = []): array
    {
        $results = [
            'total' => count($userIds),
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($userIds as $userId) {
            $result = $this->sendToUser($userId, $message, $type, $data);
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][$userId] = $result;
        }

        return $results;
    }

    /**
     * Send wallet low balance notification
     */
    public function sendWalletLowNotification(string $userBigId, string $balance, string $username): array
    {
        return $this->sendToUserByBigId(
            $userBigId,
            'Low Wallet Balance Alert',
            "Your SMS wallet balance is low (£{$balance}). Top up now to continue sending messages.",
            UserNotification::TYPE_WALLET_LOW,
            [
                'balance' => $balance,
                'username' => $username,
                'action' => 'top_up_wallet',
                'screen' => 'BuySms',
            ]
        );
    }

    /**
     * Send wallet insufficient funds notification
     */
    public function sendWalletInsufficientNotification(string $userBigId, string $username): array
    {
        return $this->sendToUserByBigId(
            $userBigId,
            'Insufficient Funds Alert',
            "SMS send failed due to insufficient wallet funds. Please top up your account to continue sending.",
            UserNotification::TYPE_WALLET_INSUFFICIENT,
            [
                'username' => $username,
                'action' => 'top_up_wallet',
                'screen' => 'BuySms',
            ]
        );
    }

    /**
     * Send throughput limit notification
     */
    public function sendThroughputLimitNotification(string $userBigId, string $username, int $limit, int $sent): array
    {
        return $this->sendToUserByBigId(
            $userBigId,
            'Daily SMS Limit Reached',
            "You have reached your daily SMS limit of {$limit} messages. The limit will reset at midnight.",
            UserNotification::TYPE_THROUGHPUT_LIMIT,
            [
                'username' => $username,
                'limit' => (string) $limit,
                'sent' => (string) $sent,
                'action' => 'view_limits',
                'screen' => 'Dashboard',
            ]
        );
    }

    /**
     * Send general system notification
     */
    public function sendSystemNotification(string $userBigId, string $title, string $message, array $data = []): array
    {
        return $this->sendToUserByBigId(
            $userBigId,
            $title,
            $message,
            UserNotification::TYPE_SYSTEM,
            $data
        );
    }

    /**
     * Test FCM connection
     */
    public function testConnection(): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Push notifications not enabled or service account file not found',
                'path' => $this->serviceAccountPath,
                'exists' => file_exists($this->serviceAccountPath),
            ];
        }

        $accessToken = $this->getAccessToken();
        
        if ($accessToken) {
            return [
                'success' => true,
                'message' => 'FCM v1 API connection successful',
                'project_id' => $this->fcmProjectId,
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to authenticate with FCM',
        ];
    }
}
