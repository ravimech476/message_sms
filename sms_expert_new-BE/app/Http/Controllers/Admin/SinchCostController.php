<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Country;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SinchCostExport;
use App\Exports\SinchCostTemplateExport;
use App\Imports\SinchCostImport;

class SinchCostController extends Controller
{
    /**
     * Display Sinch cost management page
     */
    public function index()
    {
        $countries = Country::select(
            'id', 'name', 'iso_code', 'dialcode',
            'sinch_cost_price_gbp', 'sinch_price_updated_at'
        )
            ->orderBy('name')
            ->get();

        return view('admin.cost.sinch.index', compact('countries'));
    }

    /**
     * Update Sinch cost for a country (GBP only)
     */
    public function updateCost(Request $request, $countryId)
    {
        $request->validate([
            'sinch_cost_price_gbp' => 'required|numeric|min:0|max:999',
        ]);

        try {
            $country = Country::findOrFail($countryId);

            $costGBP = floatval($request->sinch_cost_price_gbp);

            $country->update([
                'sinch_cost_price_gbp' => $costGBP,
                'sinch_price_updated_at' => now(),
            ]);

            // Country row changed → rebuild the no-TTL country cache so sends see the new price.
            app(\App\Services\TableCache::class)->rebuildCountries();

            Log::info('Sinch country cost updated', [
                'country_id' => $countryId,
                'country_name' => $country->name,
                'sinch_cost_gbp' => $costGBP,
                'admin_id' => session('admin_user')['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Sinch cost updated for {$country->name}",
                'data' => [
                    'sinch_cost_price_gbp' => $costGBP,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update Sinch country cost', [
                'country_id' => $countryId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update cost: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download Sinch cost template
     */
    public function downloadTemplate()
    {
        return Excel::download(new SinchCostTemplateExport, 'sinch_cost_template.xlsx');
    }

    /**
     * Export current Sinch costs
     */
    public function exportCosts()
    {
        $filename = 'sinch_costs_' . now()->format('Y-m-d_His') . '.xlsx';
        return Excel::download(new SinchCostExport, $filename);
    }

    /**
     * Import Sinch costs from Excel
     */
    public function importCosts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $import = new SinchCostImport();
            Excel::import($import, $request->file('file'));

            $updatedCount = $import->getUpdatedCount();
            $errors = $import->getErrors();

            Log::info('Sinch costs imported', [
                'updated_count' => $updatedCount,
                'errors_count' => count($errors),
                'admin_id' => session('admin_user')['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} Sinch costs",
                'updated_count' => $updatedCount,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to import Sinch costs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import: ' . $e->getMessage()
            ], 500);
        }
    }
}
