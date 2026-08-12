<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Global Pricing — its own sidebar menu (not a Settings tab).
 *
 * Owns the editable pricing settings: the new-customer onboarding SMS rate AND the
 * customer-facing contracts pricing block. Values are stored in customer_settings
 * (key/value) and read back by the customer /contracts page and the add-customer form.
 *
 * Access is gated by the can_manage_global_pricing permission (auto-granted to all
 * admins via migration; super admins always allowed).
 */
class GlobalPricingController extends Controller
{
    /**
     * Single source of truth for the editable pricing settings. key => [default, cast type].
     * Used to load (this page + contracts page + add-customer form), validate, and save.
     */
    public static function pricingDefinition(): array
    {
        return [
            // Onboarding: default UK SMS rate applied to a NEW customer's routes (must be >= cost).
            'onboarding_sms_rate'                => ['default' => 0.0457,       'type' => 'decimal'],
            // Contracts page (display-only for customers, editable here).
            'contract_effective_date'            => ['default' => '2025-09-01', 'type' => 'string'],
            'contract_virtual_number_price_year' => ['default' => 250,          'type' => 'decimal'],
            'contract_overseas_rate_pence'       => ['default' => 0.6,          'type' => 'decimal'],
            'contract_uk_tier1_upto'             => ['default' => 20000,        'type' => 'integer'],
            'contract_uk_tier1_rate'             => ['default' => 0.0457,       'type' => 'decimal'],
            'contract_uk_tier2_upto'             => ['default' => 50000,        'type' => 'integer'],
            'contract_uk_tier2_rate'             => ['default' => 0.0390,       'type' => 'decimal'],
            'contract_uk_tier3_rate'             => ['default' => 0.0356,       'type' => 'decimal'],
        ];
    }

    /** Current pricing values (stored value or default) — for this page, add-customer form, contracts page. */
    public static function getPricingValues(): array
    {
        $out = [];
        foreach (self::pricingDefinition() as $key => $meta) {
            $out[$key] = CustomerSetting::getValue($key, $meta['default']);
        }
        return $out;
    }

    /**
     * Show the Global Pricing page.
     *
     * Access is enforced by the admin.permission:can_manage_global_pricing middleware on the
     * route — the same DB-backed hasPermission() check the sidebar menu uses — so menu
     * visibility and page access always agree (no stale-session 403s).
     */
    public function index()
    {
        return view('admin.global-pricing.index', [
            'p' => self::getPricingValues(),
        ]);
    }

    /** Save the Global Pricing form. Access gated by route middleware (can_manage_global_pricing). */
    public function save(Request $request)
    {
        $request->validate([
            'onboarding_sms_rate'                => 'required|numeric|min:0',
            'contract_effective_date'            => 'required|date',
            'contract_virtual_number_price_year' => 'required|numeric|min:0',
            'contract_overseas_rate_pence'       => 'required|numeric|min:0',
            'contract_uk_tier1_upto'             => 'required|integer|min:0',
            'contract_uk_tier1_rate'             => 'required|numeric|min:0',
            'contract_uk_tier2_upto'             => 'required|integer|min:0',
            'contract_uk_tier2_rate'             => 'required|numeric|min:0',
            'contract_uk_tier3_rate'             => 'required|numeric|min:0',
        ]);

        $adminUser = Session::get('admin_user');
        $adminId = $adminUser['id'] ?? null;
        foreach (self::pricingDefinition() as $key => $meta) {
            if ($request->has($key)) {
                CustomerSetting::setValue($key, $request->input($key), $meta['type'], null, $adminId);
            }
        }

        return redirect()->route('admin.global-pricing.index')
            ->with('pricing_success', 'Pricing settings saved.');
    }
}
