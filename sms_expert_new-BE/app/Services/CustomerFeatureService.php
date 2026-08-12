<?php

namespace App\Services;

use App\Models\CustomerFeature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Customer Feature Service - OLD SYSTEM Compatible
 *
 * Handles customer-specific feature flags and behaviors.
 * Replaces hardcoded conditions from OLD SYSTEM files:
 * - smssend.inc
 * - cp2_sendsms.inc
 * - cp2_sendsms_process.inc
 * - sms.mes
 *
 * Features:
 * - UTF-8 decode for message length checking
 * - Priority queue for high-priority campaigns
 * - Route override for test accounts
 * - Debug mode for showing execution info
 * - Test mode for skipping actual SMS
 * - Route fix for auto-correcting routes
 */
class CustomerFeatureService
{
    /**
     * Cache TTL in seconds (5 minutes)
     */
    const CACHE_TTL = 300;

    /**
     * Get customer features with caching
     *
     * @param string $bigid Customer bigid
     * @return CustomerFeature|null
     */
    public function getFeatures(string $bigid): ?CustomerFeature
    {
        $cacheKey = "customer_features:{$bigid}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($bigid) {
            return CustomerFeature::getByBigid($bigid);
        });
    }

    /**
     * Check if UTF-8 decode should be applied for message length checking
     * OLD SYSTEM: Used for specific customers like Leadbyte, Papersky, DMLS, etc.
     *
     * @param string $bigid Customer bigid
     * @param string|null $masterUsername Master account username
     * @return bool
     */
    public function shouldUtf8Decode(string $bigid, ?string $masterUsername = null): bool
    {
        $feature = $this->getFeatures($bigid);
        if ($feature && $feature->utf8_decode) {
            return true;
        }

        // Check master account for sub-accounts
        if ($masterUsername) {
            $cacheKey = "customer_features_master:{$masterUsername}";
            $masterFeature = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($masterUsername) {
                return CustomerFeature::getByMasterUsername($masterUsername);
            });

            if ($masterFeature && $masterFeature->utf8_decode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process message for UTF-8 decode if needed
     *
     * @param string $message Original message
     * @param string $bigid Customer bigid
     * @param string|null $masterUsername Master account username
     * @return string Processed message for length checking
     */
    public function processMessageForLengthCheck(string $message, string $bigid, ?string $masterUsername = null): string
    {
        if ($this->shouldUtf8Decode($bigid, $masterUsername)) {
            return utf8_decode($message);
        }
        return $message;
    }

    /**
     * Get priority daemon ID if customer has priority queue enabled
     * OLD SYSTEM: Chris Sebire got basedaemonid = 100 for route "p"
     *
     * @param string $bigid Customer bigid
     * @param string|null $route Current route
     * @return int|null Daemon ID if priority enabled, null otherwise
     */
    public function getPriorityDaemonId(string $bigid, ?string $route = null): ?int
    {
        $feature = $this->getFeatures($bigid);
        if (!$feature || !$feature->priority_queue) {
            return null;
        }

        // Check if route matches (if specified)
        if ($feature->priority_route !== null && $route !== null) {
            if (strtolower($route) !== strtolower($feature->priority_route)) {
                return null;
            }
        }

        return $feature->priority_daemon_id ?? 100;
    }

    /**
     * Check if customer has route override capability
     * OLD SYSTEM: Steve's test accounts could use special routes
     *
     * @param string $bigid Customer bigid
     * @return bool
     */
    public function hasRouteOverride(string $bigid): bool
    {
        $feature = $this->getFeatures($bigid);
        return $feature && $feature->route_override;
    }

    /**
     * Check if debug mode is enabled for customer
     * OLD SYSTEM: Steve's account showed debug execution time
     *
     * @param string $bigid Customer bigid
     * @return bool
     */
    public function isDebugMode(string $bigid): bool
    {
        $feature = $this->getFeatures($bigid);
        return $feature && $feature->debug_mode;
    }

    /**
     * Check if test mode is enabled (skip actual SMS sending)
     * OLD SYSTEM: Test account inserted to smsg_buffer instead of actual send
     *
     * @param string $bigid Customer bigid
     * @return bool
     */
    public function isTestMode(string $bigid): bool
    {
        $feature = $this->getFeatures($bigid);
        return $feature && $feature->test_mode;
    }

    /**
     * Check and apply route fix
     * OLD SYSTEM: Brillchris route 8 was auto-changed to 7
     *
     * @param string $bigidOrUsername Customer bigid or username
     * @param string $route Current route
     * @return array ['route' => string, 'fixed' => bool]
     */
    public function applyRouteFix(string $bigidOrUsername, string $route): array
    {
        $feature = $this->getFeatures($bigidOrUsername);

        if (!$feature) {
            // Try by username
            $cacheKey = "customer_features_username:{$bigidOrUsername}";
            $feature = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($bigidOrUsername) {
                return CustomerFeature::getByUsername($bigidOrUsername);
            });
        }

        if (!$feature || !$feature->route_fix_enabled) {
            return ['route' => $route, 'fixed' => false];
        }

        if ($feature->route_fix_from === $route) {
            $newRoute = $feature->route_fix_to;

            // Log the fix
            Log::channel('smpp_vonage')->info('Route fix applied', [
                'customer' => $bigidOrUsername,
                'from_route' => $route,
                'to_route' => $newRoute,
            ]);

            // Send notification if enabled
            if ($feature->route_fix_notify && $feature->route_fix_notify_email) {
                $this->sendRouteFixNotification($feature, $route, $newRoute);
            }

            return ['route' => $newRoute, 'fixed' => true];
        }

        return ['route' => $route, 'fixed' => false];
    }

    /**
     * Send route fix notification email
     *
     * @param CustomerFeature $feature
     * @param string $fromRoute
     * @param string $toRoute
     */
    protected function sendRouteFixNotification(CustomerFeature $feature, string $fromRoute, string $toRoute): void
    {
        try {
            $routeData = [
                'subject' => "SMS Expert: Route auto-corrected - " . now()->format('H:i:s jS M'),
                'from_route' => $fromRoute,
                'to_route' => $toRoute,
                'customer' => $feature->username ?? $feature->user_bigid,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];

            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            $emailQueueService->queueEmail(
                'App\\Mail\\RouteFixNotificationMail',
                $feature->route_fix_notify_email,
                ['route_data' => $routeData],
                [],
                5
            );
        } catch (\Exception $e) {
            Log::error('Failed to send route fix notification', [
                'error' => $e->getMessage(),
                'customer' => $feature->user_bigid,
            ]);
        }
    }

    /**
     * Clear cache for a customer
     *
     * @param string $bigid Customer bigid
     */
    public function clearCache(string $bigid): void
    {
        Cache::forget("customer_features:{$bigid}");
    }

    /**
     * Get all active features for a customer (for API/debugging)
     *
     * @param string $bigid Customer bigid
     * @return array
     */
    public function getAllFeatures(string $bigid): array
    {
        $feature = $this->getFeatures($bigid);

        if (!$feature) {
            return ['has_features' => false];
        }

        return [
            'has_features' => true,
            'utf8_decode' => $feature->utf8_decode,
            'priority_queue' => $feature->priority_queue,
            'priority_daemon_id' => $feature->priority_daemon_id,
            'priority_route' => $feature->priority_route,
            'route_override' => $feature->route_override,
            'debug_mode' => $feature->debug_mode,
            'test_mode' => $feature->test_mode,
            'route_fix_enabled' => $feature->route_fix_enabled,
            'route_fix_from' => $feature->route_fix_from,
            'route_fix_to' => $feature->route_fix_to,
        ];
    }
}
