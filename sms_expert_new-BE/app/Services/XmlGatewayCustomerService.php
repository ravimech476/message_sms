<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * XML Gateway Customer Service - OLD SYSTEM Compatible
 *
 * Handles customer-specific features for the XML to SMS Gateway.
 * Based on hardcoded conditions from OLD SYSTEM incoming_itagg_xml.php
 *
 * OLD SYSTEM Features:
 * - arunestates: Default route 7002, skip confirmations
 * - mark (2cowgreece): Default route 7002
 * - Hardys and Hansons emails: Skip confirmations
 * - Shortcode 82958: Only SpiralArm (dcd735888fac7d724773f574e7d68191)
 * - Shortcode 82466: Only specific bigid (4eea19bc689a0631f19a1ed6f4c7279f)
 */
class XmlGatewayCustomerService
{
    /**
     * Cache TTL in seconds (5 minutes)
     */
    const CACHE_TTL = 300;

    /**
     * Get default route for a customer
     *
     * @param string $bigid Customer bigid
     * @param string $username Customer username
     * @return string|null Default route or null if no override
     */
    public function getDefaultRoute(string $bigid, string $username): ?string
    {
        $cacheKey = "xml_gateway_route:{$bigid}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($bigid, $username) {
            // Check database for customer-specific route
            $customerFeature = DB::table('xml_gateway_customers')
                ->where(function ($query) use ($bigid, $username) {
                    $query->where('user_bigid', $bigid)
                          ->orWhere('username', $username);
                })
                ->where('is_active', true)
                ->first();

            if ($customerFeature && !empty($customerFeature->default_route)) {
                return $customerFeature->default_route;
            }

            return null;
        });
    }

    /**
     * Check if confirmation emails should be skipped
     *
     * @param string $username Customer username
     * @param string $fromEmail Sender's email address
     * @return bool
     */
    public function shouldSkipConfirmation(string $username, string $fromEmail): bool
    {
        $cacheKey = "xml_gateway_skip_confirm:" . md5($username . $fromEmail);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($username, $fromEmail) {
            // Check database for customer-specific skip confirmation setting
            $customerFeature = DB::table('xml_gateway_customers')
                ->where(function ($query) use ($username, $fromEmail) {
                    $query->where('username', $username)
                          ->orWhere('skip_confirmation_email', 'LIKE', '%' . strtolower($fromEmail) . '%');
                })
                ->where('is_active', true)
                ->first();

            if ($customerFeature) {
                // Check if user has skip_confirmation enabled
                if ($customerFeature->skip_confirmation) {
                    return true;
                }

                // Check if email is in the skip list
                if (!empty($customerFeature->skip_confirmation_emails)) {
                    $skipEmails = json_decode($customerFeature->skip_confirmation_emails, true) ?? [];
                    $fromEmailLower = strtolower($fromEmail);

                    foreach ($skipEmails as $email) {
                        if (strtolower($email) === $fromEmailLower) {
                            return true;
                        }
                    }
                }
            }

            return false;
        });
    }

    /**
     * Get shortcode restriction
     *
     * @param string $shortcode Shortcode/Sender ID
     * @return array|null ['restricted' => bool, 'allowed_bigid' => string]
     */
    public function getShortcodeRestriction(string $shortcode): ?array
    {
        $cacheKey = "xml_gateway_shortcode:{$shortcode}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($shortcode) {
            $restriction = DB::table('xml_gateway_shortcode_restrictions')
                ->where('shortcode', $shortcode)
                ->where('is_active', true)
                ->first();

            if ($restriction) {
                return [
                    'restricted' => true,
                    'allowed_bigid' => $restriction->allowed_bigid,
                    'notes' => $restriction->notes ?? '',
                ];
            }

            return null;
        });
    }

    /**
     * Check if user can use a specific shortcode
     *
     * @param string $shortcode Shortcode/Sender ID
     * @param string $bigid User's bigid
     * @return bool
     */
    public function canUseShortcode(string $shortcode, string $bigid): bool
    {
        $restriction = $this->getShortcodeRestriction($shortcode);

        if (!$restriction) {
            return true; // No restriction = allowed
        }

        return $restriction['allowed_bigid'] === $bigid;
    }

    /**
     * Get all features for a customer (for debugging/admin)
     *
     * @param string $bigid Customer bigid
     * @return array
     */
    public function getCustomerFeatures(string $bigid): array
    {
        $customer = DB::table('xml_gateway_customers')
            ->where('user_bigid', $bigid)
            ->where('is_active', true)
            ->first();

        if (!$customer) {
            return ['has_features' => false];
        }

        return [
            'has_features' => true,
            'default_route' => $customer->default_route,
            'skip_confirmation' => $customer->skip_confirmation,
            'skip_confirmation_emails' => json_decode($customer->skip_confirmation_emails ?? '[]', true),
            'notes' => $customer->notes,
        ];
    }

    /**
     * Clear cache for a customer
     *
     * @param string $bigid Customer bigid
     * @param string $username Customer username
     */
    public function clearCache(string $bigid, string $username = ''): void
    {
        Cache::forget("xml_gateway_route:{$bigid}");

        if (!empty($username)) {
            Cache::forget("xml_gateway_skip_confirm:" . md5($username . ''));
        }
    }
}
