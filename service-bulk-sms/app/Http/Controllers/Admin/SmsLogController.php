<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 — Sent-SMS delivery view backed by the SMPP pipeline's `smsg_log`.
 * Separate from the legacy `/messages` page (which reads `MessageUpdate`, the
 * old Vonage-REST tracking) so both data sources stay visible during cutover.
 *
 * Mirrors sms_expert's Api/Mobile/SentSmsController: same primary display field
 * (`deliverystatus2`, with `sentstatus` as tie-breaker), same status filters,
 * the "collapse raw states (acked/buffered/unknown) to In Transit" rule, and the
 * GMT→Europe/London (BST-aware) conversion for the delivery timestamp.
 */
class SmsLogController extends Controller
{
    /** Raw states that must never surface to users — shown as "In Transit". */
    private const IN_TRANSIT_RAW = [
        'unknown', 'acked', 'ack', 'buffered', 'buffered phone',
        'buffered smsc', 'accepted', 'enroute', 'en route',
    ];

    /** Selectable status filters (label shown on the tab). */
    private const FILTERS = [
        'all'       => 'All',
        'delivered' => 'Delivered',
        'failed'    => 'Failed',
        'pending'   => 'Pending',
    ];

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        if (!array_key_exists($status, self::FILTERS)) {
            $status = 'all';
        }
        $from = $request->query('from'); // Y-m-d
        $to   = $request->query('to');   // Y-m-d

        $query = DB::table('smsg_log')
            ->select([
                'id', 'mobnum', 'originator', 'text',
                'sentstatus', 'sentstatustext', 'deliverystatus2', 'deliverytime2',
                'timesubmitted', 'timesent', 'onesixty_suppliermsgref',
            ]);

        $this->applyStatusFilter($query, $status);
        $this->applyDateFilter($query, $from, $to);

        $rows = $query->orderByDesc('id')
            ->simplePaginate(50)
            ->appends($request->query());

        $rows->getCollection()->transform(function ($row) {
            [$label, $code] = $this->mapDeliveryStatus($row);
            $row->status_label = $label;
            $row->status_code  = $code;
            $row->message      = urldecode((string) $row->text);
            $row->sent_at      = $this->formatTimestamp($row->timesent ?: $row->timesubmitted, 'YmdHis');
            $row->delivered_at = $this->formatDeliveryTime($row->deliverytime2);
            return $row;
        });

        return view('admin.sms_log.index', [
            'rows'    => $rows,
            'stats'   => $this->stats(),
            'filters' => self::FILTERS,
            'status'  => $status,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    /** Production-parity WHERE clauses per status tab. */
    private function applyStatusFilter($query, string $status): void
    {
        switch ($status) {
            case 'delivered':
                $query->whereRaw("LOWER(deliverystatus2) = 'delivered'");
                break;
            case 'failed':
                $query->whereRaw("(sentstatus = 'fail' OR LOWER(deliverystatus2) IN ('non delivered','failed','rejected','expired'))");
                break;
            case 'pending':
                $query->whereRaw("sentstatus <> 'fail'
                    AND LOWER(deliverystatus2) NOT IN ('delivered','non delivered','failed','rejected','expired')
                    AND (sentstatus = 'pending' OR deliverystatus2 IS NULL OR deliverystatus2 = '' OR LOWER(deliverystatus2) = 'pending' OR LOWER(deliverystatus2) LIKE '%buffered%')");
                break;
            case 'all':
            default:
                break;
        }
    }

    /** Date range on `timesubmitted` (YYYYMMDDHHMMSS). Inclusive both ends. */
    private function applyDateFilter($query, ?string $from, ?string $to): void
    {
        if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $query->where('timesubmitted', '>=', str_replace('-', '', $from) . '000000');
        }
        if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $query->where('timesubmitted', '<=', str_replace('-', '', $to) . '235959');
        }
    }

    /**
     * Reduce (deliverystatus2, sentstatus) to a customer-facing [label, code].
     * code ∈ delivered|failed|pending|progress — maps 1:1 to a badge colour.
     */
    private function mapDeliveryStatus($row): array
    {
        $d = strtolower(trim((string) $row->deliverystatus2));
        $s = strtolower(trim((string) $row->sentstatus));

        if ($d === 'delivered') {
            return ['Delivered', 'delivered'];
        }
        if ($s === 'fail' || in_array($d, ['non delivered', 'failed', 'rejected', 'expired'], true)) {
            return ['Failed', 'failed'];
        }
        if ($s === 'pending' || $d === '' || $d === 'pending') {
            return ['Pending', 'pending'];
        }
        if (in_array($d, self::IN_TRANSIT_RAW, true)) {
            return ['In Transit', 'progress'];
        }
        return [ucfirst($d ?: 'In Transit'), 'progress'];
    }

    /** Aggregate counts for the summary cards (all-time, unfiltered). */
    private function stats(): array
    {
        // Buckets mirror the per-row badge precedence (delivered > failed > pending)
        // so they stay mutually exclusive and sum sanely against the total.
        $agg = DB::table('smsg_log')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(LOWER(deliverystatus2) = 'delivered') AS delivered")
            ->selectRaw("SUM(sentstatus = 'fail' OR LOWER(deliverystatus2) IN ('non delivered','failed','rejected','expired')) AS failed")
            ->selectRaw("SUM(sentstatus <> 'fail' AND LOWER(deliverystatus2) NOT IN ('delivered','non delivered','failed','rejected','expired') AND (sentstatus = 'pending' OR deliverystatus2 IS NULL OR deliverystatus2 = '' OR LOWER(deliverystatus2) = 'pending')) AS pending")
            ->first();

        $total     = (int) ($agg->total ?? 0);
        $delivered = (int) ($agg->delivered ?? 0);

        return [
            'total'         => $total,
            'delivered'     => $delivered,
            'failed'        => (int) ($agg->failed ?? 0),
            'pending'       => (int) ($agg->pending ?? 0),
            'delivery_rate' => $total > 0 ? round($delivered / $total * 100, 1) : 0.0,
        ];
    }

    /** YYYYMMDDHHMMSS (14) / YYYYMMDDHHMM (12) → "d M Y H:i"; blank/zero → em dash. */
    private function formatTimestamp(?string $raw, string $format = 'YmdHis'): string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || (int) $raw === 0) {
            return '—';
        }
        $len = $format === 'YmdHi' ? 12 : 14;
        $dt = \DateTime::createFromFormat($format, substr($raw, 0, $len));
        return $dt ? $dt->format('d M Y H:i') : $raw;
    }

    /**
     * `deliverytime2` is the Vonage DLR done_date in GMT/UTC. SMPP DLR text carries
     * a 10-digit YYMMDDHHMM (2-digit year); older/other feeds may use 12-digit
     * YYYYMMDDHHMM. Parse by length, then convert GMT→Europe/London so BST shows
     * +1h — mirrors sms_expert's display rule via Carbon's DST engine.
     */
    private function formatDeliveryTime(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || (int) $raw === 0) {
            return '—';
        }
        $format = strlen($raw) >= 12 ? 'YmdHi' : 'ymdHi';
        $len    = strlen($raw) >= 12 ? 12 : 10;
        try {
            return Carbon::createFromFormat($format, substr($raw, 0, $len), 'UTC')
                ->setTimezone('Europe/London')
                ->format('d M Y H:i');
        } catch (\Throwable $e) {
            return $raw;
        }
    }
}
