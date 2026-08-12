<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DashboardApiController extends Controller
{
    public function getRecentActivity(Request $request)
    {
        $userInfo = Session::get('user_info');
        $bigid = $userInfo['bigid'];
        
        // Get recent SMS activities
        $recentSms = DB::table('smsg_log')
            ->select('timesent', 'msgdest', 'sentstatus', 'userprice', 'msgtext')
            ->where('userref', $bigid)
            ->orderBy('timesent', 'desc')
            ->limit(20)
            ->get();

        $activities = [];
        
        foreach ($recentSms as $sms) {
            $timeAgo = $this->timeAgo($sms->timesent);
            $phone = substr($sms->msgdest, 0, 6) . '****'; // Mask phone number for privacy
            $status = $sms->sentstatus;
            
            $activity = [
                'icon' => $this->getStatusIcon($status),
                'color' => $this->getStatusColor($status),
                'text' => $this->getActivityText($status, $phone, $sms->userprice),
                'time' => $timeAgo
            ];
            
            $activities[] = $activity;
        }
        
        return response()->json($activities);
    }
    
    public function getLiveStats(Request $request)
    {
        $userInfo = Session::get('user_info');
        $bigid = $userInfo['bigid'];
        
        // Get stats for the last hour
        $oneHourAgo = Carbon::now()->subHour()->format('YmdHis');
        $now = Carbon::now()->format('YmdHis');
        
        $hourlyStats = DB::table('smsg_log')
            ->selectRaw('
                COUNT(*) as sent_last_hour,
                SUM(CASE WHEN sentstatus = "ok" THEN 1 ELSE 0 END) as delivered_last_hour,
                SUM(userprice) as spent_last_hour
            ')
            ->where('userref', $bigid)
            ->where('timesent', '>=', $oneHourAgo)
            ->where('timesent', '<=', $now)
            ->first();
            
        // Get today's stats
        $todayStart = Carbon::now()->format('Ymd') . '000000';
        $todayEnd = Carbon::now()->format('Ymd') . '235959';
        
        $todayStats = DB::table('smsg_log')
            ->selectRaw('
                COUNT(*) as sent_today,
                SUM(CASE WHEN sentstatus = "ok" THEN 1 ELSE 0 END) as delivered_today,
                SUM(userprice) as spent_today
            ')
            ->where('userref', $bigid)
            ->where('timesent', '>=', $todayStart)
            ->where('timesent', '<=', $todayEnd)
            ->first();
            
        // Get delivery rate trends
        $deliveryRate = $todayStats->sent_today > 0 ? 
            round(($todayStats->delivered_today / $todayStats->sent_today) * 100, 2) : 0;
            
        return response()->json([
            'hourly' => [
                'sent' => $hourlyStats->sent_last_hour ?? 0,
                'delivered' => $hourlyStats->delivered_last_hour ?? 0,
                'spent' => $hourlyStats->spent_last_hour ?? 0
            ],
            'today' => [
                'sent' => $todayStats->sent_today ?? 0,
                'delivered' => $todayStats->delivered_today ?? 0,
                'spent' => $todayStats->spent_today ?? 0,
                'delivery_rate' => $deliveryRate
            ],
            'timestamp' => Carbon::now()->toISOString()
        ]);
    }
    
    public function getHourlyTrends(Request $request)
    {
        $userInfo = Session::get('user_info');
        $bigid = $userInfo['bigid'];
        
        // Get hourly data for the last 24 hours
        $data = [];
        for ($i = 23; $i >= 0; $i--) {
            $hourStart = Carbon::now()->subHours($i)->format('YmdH') . '0000';
            $hourEnd = Carbon::now()->subHours($i)->format('YmdH') . '5959';
            
            $hourlyData = DB::table('smsg_log')
                ->selectRaw('
                    COUNT(*) as sent,
                    SUM(CASE WHEN sentstatus = "ok" THEN 1 ELSE 0 END) as delivered
                ')
                ->where('userref', $bigid)
                ->where('timesent', '>=', $hourStart)
                ->where('timesent', '<=', $hourEnd)
                ->first();
                
            $data[] = [
                'hour' => Carbon::now()->subHours($i)->format('H:i'),
                'sent' => $hourlyData->sent ?? 0,
                'delivered' => $hourlyData->delivered ?? 0
            ];
        }
        
        return response()->json($data);
    }
    
    public function getTopDestinations(Request $request)
    {
        $userInfo = Session::get('user_info');
        $bigid = $userInfo['bigid'];
        
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        
        $startDateDb = Carbon::parse($startDate)->format('Ymd') . '000000';
        $endDateDb = Carbon::parse($endDate)->format('Ymd') . '235959';
        
        // Get top destination networks/countries
        $topDestinations = DB::table('smsg_log')
            ->selectRaw('
                SUBSTR(msgdest, 1, 5) as network_prefix,
                COUNT(*) as message_count,
                SUM(userprice) as total_cost,
                SUM(CASE WHEN sentstatus = "ok" THEN 1 ELSE 0 END) as delivered_count
            ')
            ->where('userref', $bigid)
            ->where('timesent', '>=', $startDateDb)
            ->where('timesent', '<=', $endDateDb)
            ->groupBy(DB::raw('SUBSTR(msgdest, 1, 5)'))
            ->orderBy('message_count', 'desc')
            ->limit(10)
            ->get();
            
        return response()->json($topDestinations);
    }
    
    private function timeAgo($timestamp)
    {
        $time = Carbon::createFromFormat('YmdHis', $timestamp);
        return $time->diffForHumans();
    }
    
    private function getStatusIcon($status)
    {
        switch (strtolower($status)) {
            case 'ok':
                return 'check_circle';
            case 'failed':
            case 'error':
                return 'error';
            case 'pending':
                return 'hourglass_empty';
            default:
                return 'send';
        }
    }
    
    private function getStatusColor($status)
    {
        switch (strtolower($status)) {
            case 'ok':
                return 'gradient-success';
            case 'failed':
            case 'error':
                return 'gradient-danger';
            case 'pending':
                return 'gradient-warning';
            default:
                return 'gradient-primary';
        }
    }
    
    private function getActivityText($status, $phone, $cost)
    {
        $costFormatted = '£' . number_format($cost, 2);
        
        switch (strtolower($status)) {
            case 'ok':
                return "SMS delivered to {$phone} ({$costFormatted})";
            case 'failed':
            case 'error':
                return "SMS failed to {$phone} ({$costFormatted})";
            case 'pending':
                return "SMS pending to {$phone} ({$costFormatted})";
            default:
                return "SMS sent to {$phone} ({$costFormatted})";
        }
    }
}
