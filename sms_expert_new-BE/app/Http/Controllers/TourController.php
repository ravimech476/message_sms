<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class TourController extends Controller
{
    /**
     * Get tour status for current user
     */
    public function status(Request $request)
    {
        $userInfo = Session::get('user_info');
        if (!$userInfo || !isset($userInfo['bigid'])) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userOption = DB::table('useroption')
            ->where('userref', $userInfo['bigid'])
            ->first();

        return response()->json([
            'customer_tour_completed' => $userOption->customer_tour_completed ?? false,
            'campaign_tour_completed' => $userOption->campaign_tour_completed ?? false,
            'customer_tour_completed_at' => $userOption->customer_tour_completed_at ?? null,
            'campaign_tour_completed_at' => $userOption->campaign_tour_completed_at ?? null,
        ]);
    }

    /**
     * Mark customer tour as completed
     */
    public function completeCustomerTour(Request $request)
    {
        $userInfo = Session::get('user_info');
        if (!$userInfo || !isset($userInfo['bigid'])) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DB::table('useroption')
            ->where('userref', $userInfo['bigid'])
            ->update([
                'customer_tour_completed' => true,
                'customer_tour_completed_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer tour marked as completed'
        ]);
    }

    /**
     * Mark campaign tour as completed
     */
    public function completeCampaignTour(Request $request)
    {
        $userInfo = Session::get('user_info');
        if (!$userInfo || !isset($userInfo['bigid'])) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DB::table('useroption')
            ->where('userref', $userInfo['bigid'])
            ->update([
                'campaign_tour_completed' => true,
                'campaign_tour_completed_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Campaign tour marked as completed'
        ]);
    }

    /**
     * Reset customer tour (for restart)
     */
    public function resetCustomerTour(Request $request)
    {
        $userInfo = Session::get('user_info');
        if (!$userInfo || !isset($userInfo['bigid'])) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DB::table('useroption')
            ->where('userref', $userInfo['bigid'])
            ->update([
                'customer_tour_completed' => false,
                'customer_tour_completed_at' => null,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer tour reset successfully'
        ]);
    }

    /**
     * Reset campaign tour (for restart)
     */
    public function resetCampaignTour(Request $request)
    {
        $userInfo = Session::get('user_info');
        if (!$userInfo || !isset($userInfo['bigid'])) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DB::table('useroption')
            ->where('userref', $userInfo['bigid'])
            ->update([
                'campaign_tour_completed' => false,
                'campaign_tour_completed_at' => null,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Campaign tour reset successfully'
        ]);
    }
}
