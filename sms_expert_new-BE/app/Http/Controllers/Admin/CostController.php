<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;
use Carbon\Carbon;

class CostController extends Controller
{
    /**
     * Display Cost management page with Vonage and Sinch tabs
     */
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'vonage');

        // Get all countries for both tabs
        $countries = Country::select(
            'id', 'name', 'iso_code', 'dialcode',
            'cost_price_eur', 'cost_price_gbp', 'price_update_mode', 'updated_at',
            'sinch_cost_price_gbp', 'sinch_price_updated_at'
        )
            ->orderBy('name')
            ->get();

        // Get exchange rate and its update time
        $exchangeRateData = Country::whereNotNull('exchange_rate_eur_to_gbp')
            ->where('exchange_rate_eur_to_gbp', '>', 0)
            ->orderBy('exchange_rate_updated_at', 'desc')
            ->select('exchange_rate_eur_to_gbp', 'exchange_rate_updated_at')
            ->first();

        // ->first() returns null when no country has a non-zero exchange
        // rate set yet (fresh install / dev DB). Use nullsafe access so the
        // page renders with the default rate instead of throwing.
        $exchangeRate = $exchangeRateData?->exchange_rate_eur_to_gbp ?? 0.85;
        $exchangeRateUpdatedAt = $exchangeRateData?->exchange_rate_updated_at
            ? Carbon::parse($exchangeRateData->exchange_rate_updated_at)->format('d M Y H:i')
            : null;

        return view('admin.cost.index', compact('countries', 'exchangeRate', 'exchangeRateUpdatedAt', 'activeTab'));
    }
}
