<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Traits\LegacyCustomerList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Exports\PostPayInvoiceExport;
use App\Exports\DailySmsReportExport;
use App\Exports\MoneyTransferredExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Models\ReportJob;
use App\Services\Queue\RabbitMQService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class ReportsController extends Controller
{
    use LegacyCustomerList;

    /**
     * Excluded test/demo account bigids
     */
    protected $excludedBigids = [
        'q43786f4ae53946dfa8aa3def2fbd53e',
        '6641b01402fe76dd6656c16bc9c38700',
        '65f050e205dff82f529eae1c6c133bb9',
        '73419c0c137c96c84a4490545e731838',
        'v9vex6kfd8d424b6978je2er53c65dfb',
        'a33b52c6e9gd72f94fe6dbb6ccfdc57c',
    ];
    /**
     * Display the reports page with tabs
     */
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'postpay');
        // The trait now returns customers already sorted alphabetically.
        $customers = $this->getLegacyCustomers();

        return view('admin.reports.index', compact('customers', 'activeTab'));
    }

    /**
     * Get Post-Pay Report Data with filters and pagination
     */

    public function getPostPayData(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        $customerIds = $request->get('customer_ids', []);
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        if (!is_array($customerIds)) {
            $customerIds = array_filter(explode(',', $customerIds));
        }

        /*
    |--------------------------------------------------------------------------
    | Default last 6 months
    |--------------------------------------------------------------------------
    */
        $now = Carbon::now();

        if (empty($dateFrom)) {
            $dateFrom = $now->copy()->subMonths(6)->startOfMonth()->format('Y-m-d');
        }

        if (empty($dateTo)) {
            $dateTo = $now->format('Y-m-d');
        }

        $fromDate = Carbon::parse($dateFrom)->format('Ymd') . '000000';
        $toDate   = Carbon::parse($dateTo)->format('Ymd') . '235959';


        /*
    |--------------------------------------------------------------------------
    | 1. Get all smsg_log tables
    |--------------------------------------------------------------------------
    */
        $tables = DB::select("
        SELECT table_name
        FROM INFORMATION_SCHEMA.TABLES
        WHERE table_name LIKE 'smsg_log%'
        AND TABLE_SCHEMA = DATABASE()
    ");

        if (empty($tables)) {
            return response()->json(['data' => []]);
        }


        /*
    |--------------------------------------------------------------------------
    | 2. Build UNION ALL once
    |--------------------------------------------------------------------------
    */
        $unionParts = [];

        foreach ($tables as $table) {

            $tableName = $table->table_name ?? $table->TABLE_NAME;

            $unionParts[] = "
            SELECT userref, COALESCE(numparts,0) AS numparts
            FROM {$tableName}
            WHERE sentstatus = 'ok'
            AND timesent BETWEEN '{$fromDate}' AND '{$toDate}'
        ";
        }

        $unionSql = implode(' UNION ALL ', $unionParts);


        /*
    |--------------------------------------------------------------------------
    | 3. Aggregate once for ALL users (FAST)
    |--------------------------------------------------------------------------
    */
        $smsTotals = collect(DB::select("
        SELECT userref, SUM(numparts) AS total_parts
        FROM ({$unionSql}) AS logs
        GROUP BY userref
    "))->keyBy('userref');


        /*
    |--------------------------------------------------------------------------
    | 4. Build user query (SAME LOGIC)
    |--------------------------------------------------------------------------
    */
        $userQuery = DB::table('users')->where('customer_type', 'Postpaid');

        if (!empty($customerIds)) {
            $userQuery->whereIn('id', $customerIds);
        }

        if (!empty($search)) {
            $userQuery->where(function ($q) use ($search) {
                $q->where('busname', 'like', "%{$search}%")
                    ->orWhere('contactname', 'like', "%{$search}%")
                    ->orWhere('uname', 'like', "%{$search}%");
            });
        }

        $users = $userQuery->get();


        /*
    |--------------------------------------------------------------------------
    | 5. SAME calculation logic
    |--------------------------------------------------------------------------
    */
        $data = [];

        foreach ($users as $user) {

            $userRef = $user->bigid;

            $numparts = intval($smsTotals[$userRef]->total_parts ?? 0);

            // get latest rate (same as before)
            $userPrice = DB::table('smsg_userroute')
                ->where('userref', $userRef)
                ->orderByDesc('id')
                ->value('userprice') ?? '0.06';

            $totalCost = $numparts * floatval($userPrice);

            $data[] = [
                'id' => $user->id,
                'customer_name' => urldecode($user->busname),
                'contact_name' => urldecode($user->contactname),
                'username' => $user->uname,
                'total_sms' => $numparts,
                'per_sms_rate' => number_format(floatval($userPrice), 2),
                'total_cost' => number_format($totalCost, 2),
                'date_range' =>
                Carbon::parse($dateFrom)->format('d M Y') . ' - ' .
                    Carbon::parse($dateTo)->format('d M Y'),
            ];
        }


        /*
    |--------------------------------------------------------------------------
    | 6. Pagination (same)
    |--------------------------------------------------------------------------
    */
        $collection = collect($data);
        $total = $collection->count();

        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;

        $items = $collection->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $items,
            'current_page' => (int)$page,
            'per_page' => (int)$perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ]);
    }

    // public function getPostPayData(Request $request)
    // {
    //     $perPage = $request->get('per_page', 25);
    //     $search = $request->get('search', '');
    //     $customerIds = $request->get('customer_ids', []);
    //     $dateFrom = $request->get('date_from', '');
    //     $dateTo = $request->get('date_to', '');

    //     // Convert to array if not already
    //     if (!is_array($customerIds)) {
    //         $customerIds = array_filter(explode(',', $customerIds));
    //     }
    //     $customerIds = array_filter($customerIds); // Remove empty values

    //     // Default to last 6 months if no date range specified
    //     $now = Carbon::now();
    //     if (empty($dateFrom)) {
    //         $dateFrom = $now->copy()->subMonths(6)->startOfMonth()->format('Y-m-d');
    //     }
    //     if (empty($dateTo)) {
    //         $dateTo = $now->format('Y-m-d');
    //     }

    //     $fromDate = Carbon::parse($dateFrom)->format('Ymd') . '000000';
    //     $toDate = Carbon::parse($dateTo)->format('Ymd') . '235959';

    //     // Build user query
    //     $userQuery = DB::table('users')->where('customer_type', 'Postpaid');

    //     if (!empty($customerIds)) {
    //         $userQuery->whereIn('id', $customerIds);
    //     }

    //     if (!empty($search)) {
    //         $userQuery->where(function ($q) use ($search) {
    //             $q->where('busname', 'like', "%{$search}%")
    //                 ->orWhere('contactname', 'like', "%{$search}%")
    //                 ->orWhere('uname', 'like', "%{$search}%");
    //         });
    //     }

    //     $users = $userQuery->get();

    //     $data = [];
    //     foreach ($users as $user) {
    //         $userRef = $user->bigid;

    //         // Total SMS parts
    //         $numparts = DB::table('smsg_log')
    //             ->where('userref', $userRef)
    //             ->whereBetween('timesent', [$fromDate, $toDate])
    //             ->where('sentstatus', 'ok')
    //             ->sum('numparts');

    //         // Get SMS rate
    //         $userPrice = DB::table('smsg_userroute')
    //             ->where('userref', $userRef)
    //             ->orderByDesc('id')
    //             ->value('userprice') ?? '0.06';

    //         $totalCost = $numparts * floatval($userPrice);

    //         $data[] = [
    //             'id' => $user->id,
    //             'customer_name' => urldecode($user->busname),
    //             'contact_name' => urldecode($user->contactname),
    //             'username' => $user->uname,
    //             'total_sms' => $numparts,
    //             'per_sms_rate' => number_format(floatval($userPrice), 2),
    //             'total_cost' => number_format($totalCost, 2),
    //             'date_range' => Carbon::parse($dateFrom)->format('d M Y') . ' - ' . Carbon::parse($dateTo)->format('d M Y'),
    //         ];
    //     }

    //     // Convert to collection and paginate manually
    //     $collection = collect($data);
    //     $total = $collection->count();
    //     $page = $request->get('page', 1);
    //     $offset = ($page - 1) * $perPage;
    //     $items = $collection->slice($offset, $perPage)->values();

    //     return response()->json([
    //         'data' => $items,
    //         'current_page' => (int) $page,
    //         'per_page' => (int) $perPage,
    //         'total' => $total,
    //         'last_page' => ceil($total / $perPage),
    //         'from' => $offset + 1,
    //         'to' => min($offset + $perPage, $total),
    //     ]);
    // }

    /**
     * Get Daily SMS Report Data with filters and pagination
     */


    public function getDailySmsData(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        $customerIds = $request->get('customer_ids', []);
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        if (!is_array($customerIds)) {
            $customerIds = array_filter(explode(',', $customerIds));
        }

        // Default today
        if (empty($dateFrom)) $dateFrom = Carbon::today()->format('Y-m-d');
        if (empty($dateTo))   $dateTo   = Carbon::today()->format('Y-m-d');

        $fromDate = Carbon::parse($dateFrom)->format('Ymd') . '000000';
        $toDate   = Carbon::parse($dateTo)->format('Ymd') . '235959';


        /*
    |--------------------------------------------------------------------------
    | 1. Get all smsg_log tables
    |--------------------------------------------------------------------------
    */
        $tables = DB::select("
        SELECT table_name
        FROM INFORMATION_SCHEMA.TABLES
        WHERE table_name LIKE 'smsg_log%'
        AND TABLE_SCHEMA = DATABASE()
    ");

        if (empty($tables)) {
            return response()->json(['data' => []]);
        }


        /*
    |--------------------------------------------------------------------------
    | 2. Build UNION ALL query (ONE BIG QUERY)
    |--------------------------------------------------------------------------
    */
        $unionParts = [];

        foreach ($tables as $table) {

            $tableName = $table->table_name ?? $table->TABLE_NAME;

            $unionParts[] = "
            SELECT
                userref,
                (COALESCE(profit,0) - COALESCE(hlrlookupcost,0)) AS profit,
                COALESCE(numparts,0) AS numparts,
                COALESCE(costprice,0) AS costprice,
                COALESCE(userprice,0) AS userprice
            FROM {$tableName}
            WHERE sentstatus = 'ok'
            AND timesent BETWEEN '{$fromDate}' AND '{$toDate}'
        ";
        }

        $unionSql = implode(' UNION ALL ', $unionParts);


        /*
    |--------------------------------------------------------------------------
    | 3. Aggregate once
    |--------------------------------------------------------------------------
    */
        $rows = DB::select("
        SELECT
            userref,
            SUM(profit) AS theprofit,
            SUM(numparts) AS thevolume,
            SUM(costprice) AS thecostprice,
            SUM(userprice) AS theuserprice
        FROM ({$unionSql}) AS logs
        GROUP BY userref
    ");


        $data = [];

        /*
    |--------------------------------------------------------------------------
    | 4. SAME USER LOOP (unchanged logic)
    |--------------------------------------------------------------------------
    */
        foreach ($rows as $row) {

            $user = DB::table('users')->where('bigid', $row->userref)->first();
            if (!$user) continue;

            // customer filter
            if (!empty($customerIds) && !in_array($user->id, $customerIds)) {
                continue;
            }

            // search filter (EXACT match only)
            // if (!empty($search)) {

            //     $searchLower = strtolower(trim($search));

            //     if (
            //         strtolower(urldecode($user->busname ?? '')) !== $searchLower &&
            //         strtolower(urldecode($user->contactname ?? '')) !== $searchLower &&
            //         strtolower($user->uname ?? '') !== $searchLower
            //     ) {
            //         continue;
            //     }
            // }


            // // search filter partial match
            if (!empty($search)) {
                $searchLower = strtolower($search);

                if (
                    strpos(strtolower(urldecode($user->busname ?? '')), $searchLower) === false &&
                    strpos(strtolower(urldecode($user->contactname ?? '')), $searchLower) === false &&
                    strpos(strtolower($user->uname ?? ''), $searchLower) === false
                ) {
                    continue;
                }
            }

            $costPrice = floatval($row->thecostprice);
            $userPrice = floatval($row->theuserprice);
            $profit    = floatval($row->theprofit);
            $volume    = floatval($row->thevolume);

            if ($profit == 0) {
                $profit = $userPrice - $costPrice;
            }

            $data[] = [
                'id' => $user->id,
                'customer_name' => urldecode($user->busname ?? ''),
                'contact_name'  => urldecode($user->contactname ?? ''),
                'username'      => $user->uname,
                'total_sms'     => number_format($volume),

                'cost_price' => number_format($costPrice, 4, '.', ''),
                'user_price' => number_format($userPrice, 4, '.', ''),
                'profit'     => number_format($profit, 4, '.', ''),

                'date_range' =>
                Carbon::parse($dateFrom)->format('d M Y') . ' - ' .
                    Carbon::parse($dateTo)->format('d M Y'),
            ];
        }


        /*
    |--------------------------------------------------------------------------
    | 5. SAME PAGINATION (unchanged)
    |--------------------------------------------------------------------------
    */
        $collection = collect($data);
        $total = $collection->count();

        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;

        $items = $collection->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $items,
            'current_page' => (int)$page,
            'per_page' => (int)$perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ]);
    }

    /**
     * Get Money Transferred Report Data with filters and pagination
     */
    public function getMoneyTransferredData(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        $customerIds = $request->get('customer_ids', []);
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        // Convert to array if not already
        if (!is_array($customerIds)) {
            $customerIds = array_filter(explode(',', $customerIds));
        }
        $customerIds = array_filter($customerIds); // Remove empty values

        // Default to last 90 days if no date range specified
        if (empty($dateFrom)) {
            $dateFrom = Carbon::now()->subDays(90)->format('Y-m-d');
        }
        if (empty($dateTo)) {
            $dateTo = Carbon::now()->format('Y-m-d');
        }

        $from = Carbon::parse($dateFrom)->startOfDay()->toDateTimeString();
        $to = Carbon::parse($dateTo)->endOfDay()->toDateTimeString();

        $query = DB::table('money_transfer_logs')
            ->where('status', 1)
            ->whereBetween('created', [$from, $to])
            ->orderByDesc('created');

        $logs = $query->get();

        $data = [];
        foreach ($logs as $log) {
            $fromAccount = DB::table('users')->where('uname', $log->from_account)->first();
            $toAccount = DB::table('users')->where('uname', $log->to_account)->first();
            $mainAccount = DB::table('users')->where('uname', $fromAccount->masteruname ?? '')->first();

            // Apply customer filter (multiple customers)
            if (!empty($customerIds)) {
                $fromMatch = $fromAccount && in_array($fromAccount->id, $customerIds);
                $toMatch = $toAccount && in_array($toAccount->id, $customerIds);
                if (!$fromMatch && !$toMatch) {
                    continue;
                }
            }

            // Apply search filter
            if (!empty($search)) {
                $searchLower = strtolower($search);
                $fromBusname = strtolower(urldecode($fromAccount->busname ?? ''));
                $toBusname = strtolower(urldecode($toAccount->busname ?? ''));
                $mainBusname = strtolower(urldecode($mainAccount->busname ?? ''));

                if (
                    strpos($fromBusname, $searchLower) === false &&
                    strpos($toBusname, $searchLower) === false &&
                    strpos($mainBusname, $searchLower) === false
                ) {
                    continue;
                }
            }

            $data[] = [
                'id' => $log->id,
                'date_time' => date("d M Y H:i:s", strtotime($log->created)),
                'amount' => number_format($log->amount, 2),
                'lead_account' => urldecode($mainAccount->busname ?? ''),
                'transferred_from' => urldecode($fromAccount->busname ?? ''),
                'transferred_to' => urldecode($toAccount->busname ?? ''),
                'transferred_by' => $log->created_by,
                'ip_address' => $log->ip_address,
            ];
        }

        // Convert to collection and paginate manually
        $collection = collect($data);
        $total = $collection->count();
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;
        $items = $collection->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $items,
            'current_page' => (int) $page,
            'per_page' => (int) $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ]);
    }

    /**
     * Monthly Sales Report data — ported from the OLD SYSTEM
     * steve/mynews/tools.php?func=getmonthlysales (getmonthlysalesdetail).
     *
     * Returns the last N months (default 12) of invoice revenue: per-invoice detail
     * plus month totals, split by payment method (PayPal/card vs BACS), ex-VAT.
     * Paid invoices are counted in the month of paiddate; not-yet-paid invoices in
     * the month of invoicedate. Internal/test accounts are excluded, matching the
     * legacy report.
     */
    public function getMonthlySalesData(Request $request)
    {
        // Filters: year + month (each optional = "all"), customer ids, or an explicit
        // date range. Falls back to the last 12 months when no year is chosen.
        $year  = $request->get('year');
        $month = $request->get('month');

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = $request->get('date_from');
            $to   = $request->get('date_to');
        } elseif ($year) {
            if ($month) {
                $from = sprintf('%04d-%02d-01', (int) $year, (int) $month);
                $to   = $from;
            } else {
                $from = sprintf('%04d-01-01', (int) $year);
                $to   = sprintf('%04d-12-01', (int) $year);
            }
        } else {
            $monthsBack = max(1, min(36, (int) $request->get('months', 12)));
            $to   = now('Europe/London')->format('Y-m-d');
            $from = now('Europe/London')->startOfMonth()->subMonths($monthsBack - 1)->format('Y-m-d');
        }

        $customerIds = $request->input('customer_ids', []);
        if (!is_array($customerIds)) {
            $customerIds = array_filter(explode(',', (string) $customerIds));
        }
        $customerIds = array_values(array_filter($customerIds));

        // Status filter: paid / notyetpaid / deleted (empty = all).
        $statuses = $request->input('statuses', []);
        if (!is_array($statuses)) {
            $statuses = array_filter(explode(',', (string) $statuses));
        }
        $statuses = array_values(array_filter($statuses));

        // Payment method filter: PAYPAL / BACS (empty = all).
        $methods = $request->input('methods', []);
        if (!is_array($methods)) {
            $methods = array_filter(explode(',', (string) $methods));
        }
        $methods = array_values(array_filter($methods));

        $months = (new \App\Services\MonthlySalesQuery())->forRange($from, $to, $customerIds, $statuses, $methods);

        return response()->json(['success' => true, 'months' => $months]);
    }

    /**
     * Export Post-Pay Report to Excel
     */
    public function exportPostPay(Request $request)
    {
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $customerIds = $request->get('customer_ids', []);

        // Convert to array if not already
        if (!is_array($customerIds)) {
            $customerIds = array_filter(explode(',', $customerIds));
        }
        $customerIds = array_filter($customerIds);

        $filename = 'Post_Pay_Report_' . now()->format('d-m-Y_H-i-s') . '.xlsx';
        return Excel::download(new \App\Exports\PostPayFilteredExport($dateFrom, $dateTo, $customerIds), $filename);
    }

    /**
     * Export Daily SMS Report to Excel
     */
    public function exportDailySms(Request $request)
    {
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $customerIds = $request->get('customer_ids', []);
        $search = $request->get('search', '');

        // Convert to array if not already
        if (!is_array($customerIds)) {
            $customerIds = array_filter(explode(',', $customerIds));
        }
        $customerIds = array_filter($customerIds);

        $filename = 'Daily_SMS_Report_' . now()->format('d-m-Y_H-i-s') . '.xlsx';
        return Excel::download(new \App\Exports\DailySmsFilteredExport($dateFrom, $dateTo, $customerIds, $search), $filename);
    }

    /**
     * Export Money Transferred Report to Excel
     */
    public function exportMoneyTransferred(Request $request)
    {
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $customerIds = $request->get('customer_ids', []);

        // Convert to array if not already
        if (!is_array($customerIds)) {
            $customerIds = array_filter(explode(',', $customerIds));
        }
        $customerIds = array_filter($customerIds);

        $filename = 'Money_Transferred_Report_' . now()->format('d-m-Y_H-i-s') . '.xlsx';
        return Excel::download(new \App\Exports\MoneyTransferredFilteredExport($dateFrom, $dateTo, $customerIds), $filename);
    }

    /**
     * Download Daily Invoices Report (for email links)
     * Token-based access for secure downloads from email
     */
    public function downloadDailyInvoices(Request $request)
    {
        $token = $request->get('token');
        $dateStart = $request->get('start');
        $dateEnd = $request->get('end');
        $dateFile = $request->get('file');

        // Validate token (simple hash validation)
        $expectedToken = md5($dateStart . $dateEnd . $dateFile . config('app.key'));
        if ($token !== $expectedToken) {
            abort(403, 'Invalid or expired download link');
        }

        // Generate the CSV
        $invoices = DB::table('invoices as i')
            ->join('orderitem as o', 'i.id', '=', 'o.invoiceref')
            ->join('users as u', 'o.userref', '=', 'u.bigid')
            ->selectRaw("i.id")
            ->selectRaw("IF(o.productref = 51930, 'VMNs, Keywords or Other', IF(o.productref = 20551, 'SMS Messages', 'Unknown Product')) as theproduct")
            ->selectRaw("IF(o.status IN ('order', 'invoice'), 'created', 'cancelled') as thestatus")
            ->selectRaw("IF(o.status IN ('order', 'invoice'), DATE_FORMAT(FROM_UNIXTIME(i.invoicedate), '%d/%m/%Y'), DATE_FORMAT(FROM_UNIXTIME(o.finalstatedate), '%d/%m/%Y')) as theday")
            ->select('o.nonvatprice', 'o.vatrate', 'o.fullprice', 'u.contactname', 'u.busname', 'u.bigid')
            ->where(function($query) use ($dateStart, $dateEnd) {
                $query->where(function($q) use ($dateStart, $dateEnd) {
                    $q->whereIn('o.status', ['order', 'invoice'])
                      ->whereBetween('i.invoicedate', [$dateStart, $dateEnd]);
                })->orWhere(function($q) use ($dateStart, $dateEnd) {
                    $q->where('o.status', 'orderdeleted')
                      ->whereBetween('o.finalstatedate', [$dateStart, $dateEnd]);
                });
            })
            ->orderBy('i.id')
            ->orderByRaw("IF(o.status IN ('order', 'invoice'), 'created', 'cancelled') DESC")
            ->get();

        $headers = ['InvoiceNo', 'Customer', 'InvoiceDate', 'DueDate', 'Terms', 'Location', 'Memo', 'Item(Product/Service)', 'ItemDescription', 'ItemQuantity', 'ItemRate', 'ItemAmount', 'ItemTaxCode', 'ItemTaxAmount', 'Service Date', 'Status'];
        $csvContent = implode(',', $headers) . "\n";

        foreach ($invoices as $invoice) {
            $vatAmount = '£' . number_format(($invoice->nonvatprice / 100) * $invoice->vatrate, 2);
            $nonVatPrice = '£' . number_format($invoice->nonvatprice, 2);
            $vatRate = number_format($invoice->vatrate, 2) . '%';

            $contactname = trim(urldecode($invoice->contactname ?? ''));
            $busname = trim(urldecode($invoice->busname ?? ''));

            if (empty($busname) && empty($contactname)) {
                $customer = 'No customer details';
            } elseif (empty($busname)) {
                $customer = $contactname;
            } else {
                $customer = $busname;
            }

            $row = [
                $invoice->id,
                $customer,
                $invoice->theday,
                $invoice->theday,
                'Due on receipt',
                '',
                '',
                $invoice->theproduct,
                '',
                '1',
                $nonVatPrice,
                $nonVatPrice,
                $vatRate,
                $vatAmount,
                $invoice->theday,
                $invoice->thestatus,
            ];

            $csvContent .= '"' . implode('","', array_map(function($val) {
                return str_replace('"', '""', $val);
            }, $row)) . "\"\n";
        }

        $filename = "{$dateFile}_sms_invoices.csv";

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download Client Wallets Report (for email links)
     * Token-based access for secure downloads from email
     */
    public function downloadClientWallets(Request $request)
    {
        $token = $request->get('token');
        $dateFile = $request->get('file', Carbon::now()->format('Ymd'));

        // Validate token (simple hash validation)
        $expectedToken = md5('clientwallets' . $dateFile . config('app.key'));
        if ($token !== $expectedToken) {
            abort(403, 'Invalid or expired download link');
        }

        // Generate the CSV - Only include NEW SYSTEM users (migration_flag = 'new')
        $clients = DB::table('users')
            ->selectRaw("IF(masteruname = '' OR masteruname = uname, uname, masteruname) as theusr")
            ->selectRaw('SUM(smsg_wallet - smsg_server1_sent - smsg_server2_sent) as thewallet')
            ->whereRaw('(smsg_wallet - smsg_server1_sent - smsg_server2_sent) > 0')
            ->whereNotIn('bigid', $this->excludedBigids)
            ->where('migration_flag', 'new')
            ->groupBy('theusr')
            ->havingRaw('thewallet > 0')
            ->orderBy('thewallet', 'desc')
            ->get();

        $csvContent = "Customer,Wallet,User ID\n";

        foreach ($clients as $client) {
            $userDetails = DB::table('users')
                ->select('contactname', 'busname')
                ->where('uname', $client->theusr)
                ->first();

            $wallet = '£' . number_format($client->thewallet, 2);
            $contactname = trim(urldecode($userDetails->contactname ?? ''));
            $busname = trim(urldecode($userDetails->busname ?? ''));

            if (empty($busname) && empty($contactname)) {
                $customer = 'No customer details';
            } elseif (empty($busname)) {
                $customer = $contactname;
            } else {
                $customer = $busname;
            }

            $csvContent .= '"' . str_replace('"', '""', $customer) . '","' . $wallet . '","' . $client->theusr . "\"\n";
        }

        $filename = "{$dateFile}_client_wallets.csv";

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Build the admin host base URL for report links in emails.
     *
     * The download routes live under the admin auth group, so an email
     * recipient must reach them via the admin subdomain (otherwise their
     * customer / dashboard session can't satisfy the admin middleware).
     * Falls back to APP_URL only if ADMIN_URL is not configured.
     *
     * Set in .env:   ADMIN_URL=https://admin.smsexpert.co.uk
     */
    protected static function adminBaseUrl(): string
    {
        // Email links to admin pages must open on the ADMIN host, not the customer
        // dashboard (config('app.url')). config('app.admin_url') is resolved from
        // ADMIN_FULL_URL / ADMIN_URL / ADMIN_DOMAIN and survives config:cache; the
        // env() calls are a fallback for un-cached configs. app.url is last resort.
        $url = config('app.admin_url')
            ?: env('ADMIN_FULL_URL') ?: env('ADMIN_URL') ?: env('ADMIN_DOMAIN')
            ?: config('app.url');

        $url = trim((string) $url);

        // The env may hold a COMMA-SEPARATED list of hosts (e.g. copied from
        // ADMIN_DOMAINS like "admin.smsexpert.co.uk,admin.smsexpert:8000"). Use only
        // the first one so the link isn't a broken "host1,host2/path".
        if (strpos($url, ',') !== false) {
            $url = trim(explode(',', $url)[0]);
        }

        // Ensure an absolute, clickable scheme — values like "admin.smsexpert.co.uk"
        // with no scheme produce a broken relative link in the email.
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return rtrim($url, '/');
    }

    /**
     * Generate secure URL for daily invoices download.
     * Always points at the admin host so staff log in to admin and then
     * the download fires under their authenticated admin session.
     */
    public static function generateInvoicesUrl($dateStart, $dateEnd, $dateFile)
    {
        $token = md5($dateStart . $dateEnd . $dateFile . config('app.key'));
        // `false` => return a relative path only; we prepend the admin host
        // ourselves so the link in emails opens on admin.smsexpert.co.uk
        // instead of whatever APP_URL points at (dashboard.smsexpert.co.uk).
        $path = route('admin.reports.download-invoices', [
            'token' => $token,
            'start' => $dateStart,
            'end' => $dateEnd,
            'file' => $dateFile,
        ], false);

        return self::adminBaseUrl() . $path;
    }

    /**
     * Generate secure URL for client wallets download. See generateInvoicesUrl
     * for why the admin host is used instead of APP_URL.
     */
    public static function generateClientWalletsUrl($dateFile = null)
    {
        $dateFile = $dateFile ?? Carbon::now()->format('Ymd');
        $token = md5('clientwallets' . $dateFile . config('app.key'));
        $path = route('admin.reports.download-wallets', [
            'token' => $token,
            'file' => $dateFile,
        ], false);

        return self::adminBaseUrl() . $path;
    }

    // =====================================================================
    //  Async (queued) report generation + history + download
    // =====================================================================

    /**
     * Queue a report for background generation. The user picks type + date range
     * + a name + a notification email; we store a report_jobs row and publish it
     * to RabbitMQ. The reports:consume worker builds the file and emails a link.
     */
    public function requestReport(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|in:postpay,daily_sms,money_transfer,monthly_sales',
            'date_from'   => 'required|date|before_or_equal:today',
            'date_to'     => 'required|date|after_or_equal:date_from|before_or_equal:today',
            'report_name' => 'nullable|string|max:150',
            'email'       => 'required|email',
            'search'      => 'nullable|string|max:150',
        ]);

        $customerIds = $request->input('customer_ids', []);
        if (!is_array($customerIds)) {
            $customerIds = array_filter(explode(',', (string) $customerIds));
        }
        $customerIds = array_values(array_filter($customerIds));

        $from = Carbon::parse($validated['date_from'])->format('Y-m-d');
        $to   = Carbon::parse($validated['date_to'])->format('Y-m-d');

        // Default name: "<Type> dd Mon YYYY - dd Mon YYYY"
        $name = trim($validated['report_name'] ?? '');
        if ($name === '') {
            $name = ReportJob::typeLabel($validated['report_type']) . ' '
                . Carbon::parse($from)->format('d M Y') . ' - ' . Carbon::parse($to)->format('d M Y');
        }

        $admin = (array) Session::get('admin_user', []);

        $job = ReportJob::create([
            'admin_bigid'  => $admin['bigid'] ?? ($admin['id'] ?? null),
            'admin_name'   => $admin['name'] ?? ($admin['contactname'] ?? null),
            'email'        => $validated['email'],
            'report_type'  => $validated['report_type'],
            'report_name'  => $name,
            'date_from'    => $from,
            'date_to'      => $to,
            'customer_ids' => json_encode($customerIds),
            'search'       => $validated['search'] ?? null,
            'status'       => ReportJob::STATUS_PENDING,
            'requested_at' => now('Europe/London'),
        ]);

        try {
            (new RabbitMQService())->publishToQueue(
                env('RABBITMQ_REPORTS_QUEUE', 'reports.generate'),
                ['report_job_id' => $job->id]
            );
        } catch (\Throwable $e) {
            Log::error('requestReport — queue publish failed: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'queued'  => false,
                'id'      => $job->id,
                'message' => 'Report saved but the queue is unavailable right now; it will be generated once the worker runs.',
            ]);
        }

        return response()->json([
            'success' => true,
            'queued'  => true,
            'id'      => $job->id,
            'message' => 'Report queued. You will get an email at ' . e($job->email) . ' when it is ready.',
        ]);
    }

    /**
     * Report history (most recent first) for the History panel.
     */
    public function reportHistory(Request $request)
    {
        try {
        $query = ReportJob::orderByDesc('id');
        $type = $request->get('type');
        if (in_array($type, ['postpay', 'daily_sms', 'money_transfer', 'monthly_sales'], true)) {
            $query->where('report_type', $type);
        }
        $rows = $query->limit(100)->get()->map(function (ReportJob $j) {
            return [
                'id'           => $j->id,
                'name'         => $j->report_name,
                'type'         => ReportJob::typeLabel($j->report_type),
                'date_range'   => optional($j->date_from)->format('d M Y') . ' - ' . optional($j->date_to)->format('d M Y'),
                'email'        => $j->email,
                'status'       => $j->status,
                'requested_at' => optional($j->requested_at)->format('d M Y H:i'),
                'completed_at' => optional($j->completed_at)->format('d M Y H:i'),
                'error'        => $j->error_message,
                'download_url' => $j->isReady()
                    ? route('admin.reports.download', ['id' => $j->id, 'token' => self::downloadToken($j)])
                    : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            Log::warning('reportHistory failed (report_jobs table missing? run migrate): ' . $e->getMessage());
            return response()->json(['success' => true, 'data' => [], 'message' => 'No report history yet.']);
        }
    }

    /**
     * Download a generated report (admin-authed via AdminMiddleware; the token
     * keeps the emailed link self-validating).
     */
    public function downloadReport(Request $request, $id)
    {
        $job = ReportJob::find($id);
        if (!$job || !$job->isReady()) {
            abort(404, 'Report not found or not ready yet.');
        }

        if ($request->get('token') !== self::downloadToken($job)) {
            abort(403, 'Invalid or expired download link.');
        }

        if (!Storage::disk('local')->exists($job->file_path)) {
            abort(404, 'Report file is missing.');
        }

        return Storage::disk('local')->download($job->file_path, $job->file_name);
    }

    /** Stable token securing the download link. */
    protected static function downloadToken(ReportJob $job): string
    {
        return md5('report-download' . $job->id . config('app.key'));
    }

    /** Admin-host download URL (token included) for the notification email. */
    public static function downloadUrl(ReportJob $job): string
    {
        $path = route('admin.reports.download', [
            'id'    => $job->id,
            'token' => self::downloadToken($job),
        ], false);

        return self::adminBaseUrl() . $path;
    }
}
