<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Traits\LegacyCustomerList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Livebeat report — new-system replica of the old iTAGG
 *   web/steve/mynews/toolslib.php -> livebeat()
 *
 * Groups sent/queued SMS by CLIENT + Status + Route + SMSG-Daemon, exactly like the old
 * report, and renders the same columns (Client, Volume, Delivered D/ND/Other, Net Profit,
 * Route, SMSG Daemon, Status, Age, Gross Profit, HLR Cost, Cost) plus the Summary line.
 *
 * Priority bucket:  FLOOR(MOD(smsgdaemonid,1000)/100)*100  ->  0,100,…,900
 * Route label:      SUBSTRING(chargetype,3,1) + requested_route (+ '/nonUK-<dialcode>')
 */
class DaemonReportController extends Controller
{
    use LegacyCustomerList;

    private const BUCKET = 'FLOOR(MOD(l.smsgdaemonid, 1000) / 100) * 100';

    public function index(Request $request)
    {
        // ---- Filters --------------------------------------------------------------
        // Default to TODAY (both dates) — the user can widen the range if needed.
        $fromDate = $request->input('from_date') ?: Carbon::now()->format('Y-m-d');
        $toDate   = $request->input('to_date')   ?: Carbon::now()->format('Y-m-d');
        $country  = $request->input('country', '');
        $supplier = $request->input('supplier', '');
        $status   = $request->input('sentstatus', 'all');   // default: all statuses (like old Livebeat)
        // Multi-select of users (array of bigids). Also tolerate a single string / comma list.
        $selectedUsers = $request->input('user', []);
        if (!is_array($selectedUsers)) {
            $selectedUsers = $selectedUsers === '' ? [] : preg_split('/\s*,\s*/', (string) $selectedUsers);
        }
        $selectedUsers = array_values(array_filter(array_map('trim', $selectedUsers)));

        $fromDay = str_replace('-', '', $fromDate);   // YYYYMMDD
        $toDay   = str_replace('-', '', $toDate);     // YYYYMMDD
        $nowTs   = Carbon::now()->format('YmdHis');

        // Filter on dayofyear (YYYYMMDD) — this is exactly what the old Livebeat used
        // (toolslib.php:7687  "dayofyear between ..."). It deliberately ABANDONED the
        // dosendtime filter (commented out at toolslib.php:7686): dosendtime is the
        // *scheduled* send time and is 0/blank for send-now messages, so filtering on
        // it drops almost every sent row and the report reads 0.
        $where    = ['l.dayofyear BETWEEN ? AND ?', "u.user_type <> 'legacy'"];
        $bindings = [$fromDay, $toDay];

        if ($status !== '' && $status !== 'all') {
            $where[]    = 'l.sentstatus = ?';
            $bindings[] = $status;
        }
        if ($country === 'other') {
            $where[] = "l.countrydialcode NOT IN ('44', '27')";
        } elseif ($country !== '') {
            $where[]    = 'l.countrydialcode = ?';
            $bindings[] = $country;
        }
        if ($supplier !== '') {
            $where[]    = 'l.suppliername = ?';
            $bindings[] = $supplier;
        }
        if (!empty($selectedUsers)) {
            // Each value is a users.bigid; also resolve any that were passed as a username.
            $refs = [];
            foreach ($selectedUsers as $u) {
                if (preg_match('/^[0-9a-zA-Z]{32}$/', $u)) {
                    $refs[] = $u;
                } else {
                    $bigid = DB::table('users')->where('uname', $u)->value('bigid');
                    if ($bigid) {
                        $refs[] = $bigid;
                    }
                }
            }
            if (!empty($refs)) {
                $placeholders = implode(',', array_fill(0, count($refs), '?'));
                $where[] = "l.userref IN ($placeholders)";
                array_push($bindings, ...$refs);
            } else {
                $where[] = '1 = 0';
            }
        }

        $whereSql = implode(' AND ', $where);
        $bucket   = self::BUCKET;

        // Status label — mirrors the old CASE (queued = scheduled in the future).
        $statusExpr = "
            CASE
                WHEN l.dosendtime > ?          THEN '0. queued'
                WHEN l.sentstatus = 'pause'    THEN '0. pause'
                WHEN l.sentstatus = 'pending'  THEN '1. pending'
                WHEN l.sentstatus = 'hlrwait'  THEN '2. hlrwait'
                WHEN l.sentstatus = 'no'       THEN '3. ready4phase1'
                WHEN l.sentstatus = 'firing'   THEN '4. ready2fire'
                WHEN l.sentstatus = 'doing'    THEN '5. firing'
                WHEN l.sentstatus = 'ok'       THEN '6. ok'
                WHEN l.sentstatus = 'fail'     THEN CONCAT('7. fail', IF(l.sentstatustext = 'Blacklisted number.', ' (Blacklist)', ''))
                ELSE l.sentstatus
            END";

        // Route label: chargetype basis letter + route (+ /nonUK-<dialcode>).
        $routeExpr = "CONCAT(
            SUBSTRING(l.chargetype, 3, 1),
            IF(l.countrydialcode <> '44', CONCAT(l.requested_route, '/nonUK-', l.countrydialcode), l.requested_route)
        )";

        // Daemon label (blank for fail, like the old report).
        $daemonExpr = "IF(l.sentstatus = 'fail', '',
            CONCAT(IF(l.suppliername = '', '', CONCAT(l.suppliername, ': ')), CAST($bucket AS UNSIGNED)))";

        $sql = "
            SELECT
                CONCAT(
                    IF(u.daemonpriority > 0, CONCAT(u.daemonpriority, ' '), ''),
                    u.contactname, ' - ', COALESCE(u.busname, '')
                ) AS client,
                u.uname                                                     AS uname,
                $statusExpr                                                 AS status,
                $routeExpr                                                  AS route,
                $daemonExpr                                                 AS daemon_name,
                l.suppliername                                              AS supplier,
                SUBSTRING(l.chargetype, 3, 1)                               AS charge_basis,
                SUM(l.numparts)                                             AS volume,
                SUM(IF(l.deliverystatus2 = 'Delivered', l.numparts, 0))     AS delivered,
                SUM(IF(l.deliverystatus2 = 'Non Delivered', l.numparts, 0)) AS non_delivered,
                SUM(IF(l.deliverystatus2 NOT IN ('Delivered', 'Non Delivered'), l.numparts, 0)) AS other_status,
                SUM(l.profit)                                               AS gross_profit,
                SUM(l.hlrlookupcost)                                        AS hlr_cost,
                SUM(l.costprice + l.hlrlookupcost)                          AS cost,
                SUM(l.userprice)                                            AS revenue,
                MIN(l.dosendtime)                                           AS oldest_dosendtime
            FROM smsg_log l
            JOIN users u ON u.bigid = l.userref
            WHERE $whereSql
            GROUP BY client, uname, status, route, daemon_name, supplier, charge_basis
            ORDER BY status ASC, client ASC, daemon_name ASC, route ASC
        ";

        // $nowTs is the first bind (statusExpr placeholder), then the WHERE bindings.
        $rows = DB::select($sql, array_merge([$nowTs], $bindings));

        // ---- Per-row derived values ----------------------------------------------
        $summary = [
            'submitted' => 0, 'delivered' => 0, 'non_delivered' => 0, 'other' => 0,
            'revenue' => 0, 'cost' => 0, 'gross_profit' => 0, 'hlr_cost' => 0, 'net_profit' => 0,
        ];
        $statusCounts = []; // label => volume

        foreach ($rows as $r) {
            // Some legacy names are stored URL-encoded; decode for display.
            $r->client = urldecode((string) $r->client);

            $r->net_profit = $r->gross_profit - $r->hlr_cost;

            // per-SMS divider: delivery-charged ('d') routes divide by delivered, else by volume.
            $divider = ($r->charge_basis === 'd' && $r->delivered > 0) ? $r->delivered : $r->volume;
            $r->net_pence  = $divider > 0 ? ($r->net_profit / $divider) * 100 : 0;
            $r->cost_pence = $divider > 0 ? ($r->cost / $divider) * 100 : 0;

            // Delivered / Non-delivered / Other percentages of volume.
            $r->del_pct   = $r->volume > 0 ? round(($r->delivered / $r->volume) * 100) : 0;
            $r->ndel_pct  = $r->volume > 0 ? round(($r->non_delivered / $r->volume) * 100) : 0;
            $r->other_pct = $r->volume > 0 ? round(($r->other_status / $r->volume) * 100) : 0;

            // Age only for not-yet-sent statuses (queued/pause/pending/hlrwait/ready/firing).
            $unsent = in_array(substr($r->status, 0, 1), ['0', '1', '2', '3', '4', '5'], true);
            $r->age = ($unsent && $r->oldest_dosendtime) ? $this->formatAge($r->oldest_dosendtime) : '';

            $summary['submitted']     += $r->volume;
            $summary['delivered']     += $r->delivered;
            $summary['non_delivered'] += $r->non_delivered;
            $summary['other']         += $r->other_status;
            $summary['revenue']       += $r->revenue;
            $summary['cost']          += $r->cost;
            $summary['gross_profit']  += $r->gross_profit;
            $summary['hlr_cost']      += $r->hlr_cost;
            $summary['net_profit']    += $r->net_profit;

            $label = trim(preg_replace('/^\d+\.\s*/', '', $r->status)); // "1. pending" -> "pending"
            $statusCounts[$label] = ($statusCounts[$label] ?? 0) + $r->volume;
        }

        $summary['delivered_pct']  = $summary['submitted'] > 0 ? round(($summary['delivered'] / $summary['submitted']) * 100, 2) : 0;
        $summary['per_submitted']  = $summary['submitted'] > 0 ? $summary['gross_profit'] / $summary['submitted'] : 0;
        $summary['per_delivered']  = $summary['delivered'] > 0 ? $summary['gross_profit'] / $summary['delivered'] : 0;

        $suppliers = DB::table('smsg_log')
            ->select('suppliername')->where('suppliername', '<>', '')
            ->distinct()->orderBy('suppliername')->pluck('suppliername');

        // Customer list for the multi-select (same source every admin dropdown uses).
        $customers = $this->getLegacyCustomers();

        return view('admin.reports.daemon', [
            'rows'         => $rows,
            'summary'      => $summary,
            'statusCounts' => $statusCounts,
            'suppliers'    => $suppliers,
            'customers'    => $customers,
            'filters'      => [
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'country'    => $country,
                'supplier'   => $supplier,
                'sentstatus' => $status,
                'user'       => $selectedUsers, // array of selected bigids
            ],
        ]);
    }

    /**
     * Age of the oldest queued message as "Xh Ym Zs" (old Livebeat formula).
     */
    private function formatAge(string $ts): string
    {
        if (strlen($ts) < 14) {
            return '';
        }
        $sent = mktime(
            (int) substr($ts, 8, 2), (int) substr($ts, 10, 2), (int) substr($ts, 12, 2),
            (int) substr($ts, 4, 2), (int) substr($ts, 6, 2), (int) substr($ts, 0, 4)
        );
        $diff = time() - $sent;
        if ($diff < 0) {
            return '0s';
        }
        $hrs  = intval($diff / 3600);
        $mins = intval(($diff / 60) - ($hrs * 60));
        $secs = intval($diff - ($mins * 60) - ($hrs * 3600));

        return (($hrs ? $hrs . 'h ' : '') . ($mins ? $mins . 'm ' : '') . ($secs ? $secs . 's' : '')) ?: '0s';
    }
}
