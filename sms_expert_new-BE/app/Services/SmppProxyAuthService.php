<?php

namespace App\Services;

use App\Models\SmppProxyUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

/**
 * SMPP Proxy Authentication Service - OLD SYSTEM Compatible
 *
 * Handles SMPP proxy authentication for users like Craig Rutherford.
 * Based on smssend.inc lines 270-397
 *
 * Craig Rutherford provides an SMPP front end for us and must pass &smppusr param
 * to represent the username that is used within his system. We then translate
 * this into itagg username.
 *
 * Error codes:
 * - 357: Proxy logging in but has not set smppusr param
 * - 358: Proxy logging in but with wrong password
 * - 359: Proxy set smppusr to non-existing client, or sql failed
 * - 360: Proxy set smppusr to a user that is not setup for SMPP route
 * - 361: Proxy logged in but from unauthorized IP address
 */
class SmppProxyAuthService
{
    const CACHE_TTL = 300; // 5 minutes

    // Error codes matching OLD SYSTEM
    const ERROR_MISSING_SMPPUSR = 357;
    const ERROR_WRONG_PASSWORD = 358;
    const ERROR_USER_NOT_FOUND = 359;
    const ERROR_USER_NOT_SMPP = 360;
    const ERROR_BAD_IP = 361;

    /**
     * Check if this is an SMPP proxy authentication request
     */
    public function isProxyAuth(string $username, string $password): bool
    {
        $cacheKey = "smpp_proxy_user:{$username}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($username, $password) {
            return SmppProxyUser::validateCredentials($username, $password) !== null;
        });
    }

    /**
     * Authenticate via SMPP proxy
     *
     * @param string $proxyUsername The proxy user's username
     * @param string $proxyPassword The proxy user's password
     * @param string|null $smppUsername The actual user's username (smppusr param)
     * @param string|null $ipAddress The client's IP address
     * @return array Authentication result
     */
    public function authenticate(
        string $proxyUsername,
        string $proxyPassword,
        ?string $smppUsername,
        ?string $ipAddress = null
    ): array {
        // Get the proxy user
        $proxyUser = SmppProxyUser::getByUsername($proxyUsername);

        if (!$proxyUser) {
            // Unknown proxy user - let regular auth handle it
            return ['success' => false, 'is_proxy' => false];
        }

        // Check password
        if ($proxyUser->proxy_password !== $proxyPassword) {
            $this->sendErrorNotification(
                $proxyUser,
                'Proxy logging in but with wrong password',
                $proxyUsername,
                $proxyPassword,
                $smppUsername,
                $ipAddress
            );

            Log::warning('SMPP Proxy: Wrong password', [
                'proxy_user' => $proxyUsername,
                'ip' => $ipAddress,
            ]);

            return [
                'success' => false,
                'is_proxy' => true,
                'error_code' => self::ERROR_WRONG_PASSWORD,
                'error_text' => 'bad user details',
            ];
        }

        // Check if smppusr param is set
        if (empty($smppUsername) || trim($smppUsername) === '') {
            $this->sendErrorNotification(
                $proxyUser,
                'Proxy logging in but has not set smppusr param',
                $proxyUsername,
                $proxyPassword,
                $smppUsername,
                $ipAddress
            );

            Log::warning('SMPP Proxy: Missing smppusr param', [
                'proxy_user' => $proxyUsername,
                'ip' => $ipAddress,
            ]);

            return [
                'success' => false,
                'is_proxy' => true,
                'error_code' => self::ERROR_MISSING_SMPPUSR,
                'error_text' => 'bad user details',
            ];
        }

        // Check IP address
        if ($ipAddress && !$proxyUser->isIpAllowed($ipAddress)) {
            $this->sendErrorNotification(
                $proxyUser,
                'Proxy logged in but from unauthorized IP address',
                $proxyUsername,
                $proxyPassword,
                $smppUsername,
                $ipAddress
            );

            Log::warning('SMPP Proxy: Unauthorized IP', [
                'proxy_user' => $proxyUsername,
                'ip' => $ipAddress,
                'allowed_ips' => $proxyUser->allowed_ips,
            ]);

            return [
                'success' => false,
                'is_proxy' => true,
                'error_code' => self::ERROR_BAD_IP,
                'error_text' => 'bad user details',
            ];
        }

        // Look up the actual user by smppusr
        $user = DB::table('users')
            ->where(DB::raw('LOWER(uname)'), strtolower(trim($smppUsername)))
            ->first();

        if (!$user) {
            $this->sendErrorNotification(
                $proxyUser,
                'Proxy set smppusr to non-existing client',
                $proxyUsername,
                $proxyPassword,
                $smppUsername,
                $ipAddress
            );

            Log::warning('SMPP Proxy: User not found', [
                'proxy_user' => $proxyUsername,
                'smpp_user' => $smppUsername,
                'ip' => $ipAddress,
            ]);

            return [
                'success' => false,
                'is_proxy' => true,
                'error_code' => self::ERROR_USER_NOT_FOUND,
                'error_text' => 'bad user details',
            ];
        }

        // Check if user is enabled for SMPP proxy auth
        if (empty($user->is_SMPP)) {
            $this->sendErrorNotification(
                $proxyUser,
                'Proxy set smppusr to a user that is not setup for SMPP route',
                $proxyUsername,
                $proxyPassword,
                $smppUsername,
                $ipAddress
            );

            Log::warning('SMPP Proxy: User not enabled for SMPP', [
                'proxy_user' => $proxyUsername,
                'smpp_user' => $smppUsername,
                'user_bigid' => $user->bigid,
                'ip' => $ipAddress,
            ]);

            return [
                'success' => false,
                'is_proxy' => true,
                'error_code' => self::ERROR_USER_NOT_SMPP,
                'error_text' => 'bad user details',
            ];
        }

        // Success! Return the actual user
        Log::info('SMPP Proxy: Authentication successful', [
            'proxy_user' => $proxyUsername,
            'proxy_name' => $proxyUser->proxy_name,
            'smpp_user' => $smppUsername,
            'user_bigid' => $user->bigid,
            'ip' => $ipAddress,
        ]);

        return [
            'success' => true,
            'is_proxy' => true,
            'user' => (array)$user,
            'proxy_user' => $proxyUser->proxy_name ?? $proxyUsername,
        ];
    }

    /**
     * Send error notification email
     */
    protected function sendErrorNotification(
        SmppProxyUser $proxyUser,
        string $errorMessage,
        string $username,
        string $password,
        ?string $smppUsername,
        ?string $ipAddress
    ): void {
        if (empty($proxyUser->notify_email)) {
            return;
        }

        try {
            $errorData = [
                'subject' => "SMS Expert: SMPP Proxy Authentication Error",
                'error_message' => $errorMessage,
                'ip_address' => $ipAddress,
                'username' => $username,
                'password_masked' => str_repeat('*', strlen($password)),
                'smpp_username' => $smppUsername,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];

            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            $emailQueueService->queueEmail(
                'App\\Mail\\SmppProxyAuthErrorMail',
                $proxyUser->notify_email,
                ['error_data' => $errorData],
                [],
                10 // High priority for security alerts
            );
        } catch (\Exception $e) {
            Log::error('Failed to send SMPP proxy error notification', [
                'error' => $e->getMessage(),
                'proxy_user' => $proxyUser->proxy_username,
            ]);
        }
    }

    /**
     * Clear cache for a proxy user
     */
    public function clearCache(string $username): void
    {
        Cache::forget("smpp_proxy_user:{$username}");
    }
}
