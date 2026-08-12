<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Shared Sales (monthly) report query — ported from the OLD SYSTEM
 * steve/mynews/tools.php?func=getmonthlysales (getmonthlysalesdetail).
 *
 * Returns the months in the given date range, each with invoice-line detail and
 * totals split by payment method (PayPal/card vs BACS), ex-VAT. Used by both the
 * live preview (ReportsController::getMonthlySalesData) and the Excel export
 * (SalesReportFilteredExport) so they always agree.
 */
class MonthlySalesQuery
{
    /**
     * @param string $from         Y-m-d (any day in the first month)
     * @param string $to           Y-m-d (any day in the last month)
     * @param array  $customerIds  users.id values to limit to (empty = all)
     * @return array               month buckets, newest first
     */
    public function forRange(string $from, string $to, array $customerIds = [], array $statuses = [], array $methods = []): array
    {
        $methods = array_map('strtoupper', $methods); // line method is upper-case (PAYPAL / BACS)
        $excludeBigids    = ['bd36f9820eb8a702743dace0b4e7f58a'];
        $excludeEmailLike = ['%@itagg.com', '%@email.tv', '%@secly.com'];

        $start = Carbon::parse($from)->startOfMonth();
        $end   = Carbon::parse($to)->startOfMonth();
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        // The report keys off users.bigid; map selected customer ids -> bigids.
        $customerBigids = [];
        if (!empty($customerIds)) {
            $customerBigids = DB::table('users')->whereIn('id', $customerIds)->pluck('bigid')->all();
        }

        $months = [];
        $cursor = $end->copy();
        while ($cursor->gte($start)) {
            $mStart  = $cursor->copy()->startOfMonth();
            $mEnd    = $cursor->copy()->addMonth()->startOfMonth();
            $startTs = $mStart->timestamp;
            $endTs   = $mEnd->timestamp;

            $q = DB::table('invoices as i')
                ->join('users as u', 'i.userref', '=', 'u.bigid')
                ->select(
                    'i.id as invoiceref',
                    // Payment-method classification. BACS payments are recorded with
                    // datacashreply = 'BACS Transfer' (OutstandingInvoiceController), while card/PayPal
                    // store the DataCash gateway reply or 'paypal'. The old check `= 'bacs'` never matched
                    // 'BACS Transfer', so BACS payments were mis-reported as PayPal. Match any 'bacs…'
                    // value case-insensitively so BACS is classified correctly (covers 'bacs' + 'BACS Transfer').
                    DB::raw("IF(LOWER(i.datacashreply) LIKE 'bacs%', 'bacs', 'paypal') as paymentmethod"),
                    DB::raw('i.datacashamount / 120 * 100 as paidexvat'),
                    'i.easilyamountnovat as notyetexvat',
                    'u.id as customer_id',
                    'u.contactname',
                    'u.busname',
                    'i.paiddate',
                    'i.invoicedate',
                    'i.datacashstate'
                )
                ->whereNotIn('u.bigid', $excludeBigids);

            foreach ($excludeEmailLike as $like) {
                $q->where('u.contactemail', 'not like', $like);
            }
            if (!empty($customerBigids)) {
                $q->whereIn('u.bigid', $customerBigids);
            }

            $q->where(function ($w) use ($startTs, $endTs) {
                $w->where(function ($w2) use ($startTs, $endTs) {
                    $w2->where('i.datacashstate', 'ok')->where('i.paiddate', '>=', $startTs)->where('i.paiddate', '<', $endTs);
                })->orWhere(function ($w2) use ($startTs, $endTs) {
                    $w2->where('i.datacashstate', '!=', 'ok')->where('i.invoicedate', '>=', $startTs)->where('i.invoicedate', '<', $endTs);
                });
            })->orderBy('i.paiddate');

            $invoices = $q->get();
            $refs  = $invoices->pluck('invoiceref')->all();
            $items = empty($refs) ? collect() : DB::table('orderitem')->whereIn('invoiceref', $refs)->get()->groupBy('invoiceref');

            $lines = [];
            foreach ($invoices as $inv) {
                $invItems = $items[$inv->invoiceref] ?? collect();
                $status = 'paid';
                foreach ($invItems as $it) {
                    if ($it->status === 'order') { $status = 'notyetpaid'; }
                    elseif ($it->status === 'orderdeleted') { $status = 'deleted'; }
                }
                $paid = $inv->datacashstate === 'ok';
                $name = trim(str_replace('+', ' ', urldecode($inv->busname ?: ($inv->contactname ?: ''))));
                $lines[] = [
                    'invoiceref'  => $inv->invoiceref,
                    'customer_id' => $inv->customer_id,
                    'customer'    => $name !== '' ? $name : '(no name)',
                    'date'       => $paid ? date('d M Y', (int) $inv->paiddate) : date('d M Y', (int) $inv->invoicedate),
                    'amount'     => round($paid ? (float) $inv->paidexvat : (float) $inv->notyetexvat, 2),
                    'method'     => strtoupper($inv->paymentmethod),
                    'status'     => $status,
                ];
            }

            // Status filter (paid / notyetpaid / deleted). Empty = all.
            if (!empty($statuses)) {
                $lines = array_values(array_filter($lines, fn ($l) => in_array($l['status'], $statuses, true)));
            }
            // Payment method filter (PAYPAL / BACS). Empty = all.
            if (!empty($methods)) {
                $lines = array_values(array_filter($lines, fn ($l) => in_array($l['method'], $methods, true)));
            }

            // Totals from the (filtered) lines so the header matches the shown data.
            $paypal = 0.0; $bacs = 0.0; $notyet = 0.0;
            foreach ($lines as $l) {
                if ($l['status'] === 'paid') {
                    if ($l['method'] === 'BACS') { $bacs += $l['amount']; } else { $paypal += $l['amount']; }
                } elseif ($l['status'] === 'notyetpaid') {
                    $notyet += $l['amount'];
                }
            }

            $months[] = [
                'month'            => $mStart->format('M Y'),
                'lines'            => $lines,
                'count'            => count($lines),
                'paypal_total'     => round($paypal, 2),
                'bacs_total'       => round($bacs, 2),
                'total'            => round($paypal + $bacs, 2),
                'notyetpaid_total' => round($notyet, 2),
            ];

            $cursor->subMonth();
        }

        return $months;
    }
}
