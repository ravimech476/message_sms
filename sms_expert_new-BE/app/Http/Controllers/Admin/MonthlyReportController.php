<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class MonthlyReportController extends Controller
{

    public function setSessionMonth(Request $request)
    {
        session([
            'userref' => $request->userref,
            'username' => $request->username
        ]);

        return redirect()->route('admin.monthly-report');
    }

    public function index()
    {
        $userref = session('userref');
        $username = session('username');

        if (!$userref || !$username) {
            return redirect('/')->with('error', 'Session expired or missing credentials.');
        }

        $results = DB::select("
            SELECT 
                LEFT(dateactuallysent, 8) AS Date_Sent,
                SUM(totspend) AS Client_Cost,
                SUM(totsmsparts) AS Client_Volume_Sent,
                SUM(totcost) AS iTagg_Cost,
                SUM(totprofit) AS iTagg_Profit,
                (SUM(totspend) / SUM(totsmsparts)) AS Client_Cost_per_SMS,
                (SUM(totcost) / SUM(totsmsparts)) AS iTagg_Cost_per_SMS,
                (SUM(totprofit) / SUM(totsmsparts)) AS iTagg_Profit_per_SMS,
                (0.001 * SUM(totsmsparts)) AS iTagg_Profit_per_SMS_per001,
                ((SUM(totcost) / SUM(totsmsparts)) / 0.00016) AS Percentage_Delivered
            FROM (
                SELECT 
                    LEFT(l.timesent, 8) AS dateactuallysent, 
                    SUM(l.costprice) AS totcost, 
                    SUM(l.userprice) AS totspend, 
                    SUM(l.profit) AS totprofit, 
                    SUM(l.numparts) AS totsmsparts
                FROM users u, smsg_log l
                WHERE 
                    u.bigid = l.userref
                    AND ((u.bigid = ?) OR (u.masteruname = ?))
                    AND l.timesent > '20161123000000'
                    AND l.sentstatus = 'ok'
                    AND l.userprice > 0
                GROUP BY dateactuallysent
            ) AS thelogs
            WHERE dateactuallysent > '20160201'
            GROUP BY Date_Sent
            ORDER BY Date_Sent ASC
            LIMIT 100000
        ", [$userref, $username]);

        return view('admin.reports.monthly', [
            'results' => $results,
            'countryText' => 'COUNTRIES (thecountries): all'
        ]);

        // $thecountriestxt = "<h4>COUNTRIES (thecountries): all </h4>";

        // $data = [
        //     (object)[
        //         'month' => 'April',
        //         'client_cost' => '£1000',
        //         'volume_submitted' => 50000,
        //         'percentage_delivered' => '95%',
        //         'itagg_cost' => '£800',
        //         'itagg_profit' => '£200',
        //         'client_cost_per_sms' => '£0.02',
        //         'itagg_cost_per_sms' => '£0.016',
        //         'itagg_profit_per_sms' => '£0.004'
        //     ],
        // ];

        // return view('admin.reports.monthly', compact('thecountriestxt', 'data'));

    }

    public function setSessionDay(Request $request)
    {
        session([
            'userref' => $request->userref,
            'username' => $request->username
        ]);

        return redirect()->route('admin.daily-report');
    }

    public function dailyReport()
    {
        $userref = session('userref');
        $username = session('username');

        if (!$userref || !$username) {
            return redirect('/')->with('error', 'Session expired or missing credentials.');
        }

        $results = DB::select("
            SELECT 
                LEFT(dateactuallysent, 8) AS Date_Sent,
                SUM(totspend) AS Client_Cost,
                SUM(totsmsparts) AS Client_Volume_Sent,
                SUM(totcost) AS iTagg_Cost,
                SUM(totprofit) AS iTagg_Profit,
                (SUM(totspend) / SUM(totsmsparts)) AS Client_Cost_per_SMS,
                (SUM(totcost) / SUM(totsmsparts)) AS iTagg_Cost_per_SMS,
                (SUM(totprofit) / SUM(totsmsparts)) AS iTagg_Profit_per_SMS,
                (0.001 * SUM(totsmsparts)) AS iTagg_Profit_per_SMS_per001,
                ((SUM(totcost) / SUM(totsmsparts)) / 0.00016) AS Percentage_Delivered
            FROM (
                SELECT 
                    LEFT(l.timesent, 8) AS dateactuallysent, 
                    SUM(l.costprice) AS totcost, 
                    SUM(l.userprice) AS totspend, 
                    SUM(l.profit) AS totprofit, 
                    SUM(l.numparts) AS totsmsparts
                FROM users u, smsg_log l
                WHERE 
                    u.bigid = l.userref
                    AND ((u.bigid = ?) OR (u.masteruname = ?))
                    AND l.timesent > '20161123000000'
                    AND l.sentstatus = 'ok'
                    AND l.userprice > 0
                GROUP BY dateactuallysent
            ) AS thelogs
            WHERE dateactuallysent > '20160201'
            GROUP BY Date_Sent
            ORDER BY Date_Sent ASC
            LIMIT 100000
        ", [$userref, $username]);

        return view('admin.reports.daily', [
            'results' => $results,
            'countryText' => 'COUNTRIES (thecountries): all'
        ]);
    }
}
