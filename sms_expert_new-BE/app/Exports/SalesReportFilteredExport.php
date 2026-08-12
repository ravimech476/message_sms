<?php

namespace App\Exports;

use App\Services\MonthlySalesQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sales Report Excel export — same data as the Sales Report preview / the OLD
 * getmonthlysales, filtered by date range + customers. One row per invoice line,
 * with a TOTAL row per month.
 */
class SalesReportFilteredExport implements FromCollection, WithHeadings, WithStyles
{
    protected string $dateFrom;
    protected string $dateTo;
    protected array $customerIds;
    protected array $statuses;
    protected array $methods;

    public function __construct($dateFrom = '', $dateTo = '', $customerIds = [], $statuses = [], $methods = [])
    {
        $this->dateFrom = $dateFrom ?: Carbon::now()->subMonths(11)->format('Y-m-d');
        $this->dateTo   = $dateTo ?: Carbon::now()->format('Y-m-d');
        $this->customerIds = is_array($customerIds) ? $customerIds : [];
        $this->statuses = is_array($statuses) ? $statuses : [];
        $this->methods = is_array($methods) ? $methods : [];
    }

    public function collection(): Collection
    {
        $months = (new MonthlySalesQuery())->forRange($this->dateFrom, $this->dateTo, $this->customerIds, $this->statuses, $this->methods);

        $rows = collect();
        foreach ($months as $m) {
            foreach ($m['lines'] as $l) {
                $rows->push([
                    $m['month'],
                    $l['invoiceref'],
                    $l['date'],
                    $l['customer'],
                    $l['method'],
                    ucfirst(str_replace('notyetpaid', 'Not yet paid', $l['status'])),
                    number_format($l['amount'], 2),
                ]);
            }
            // Per-month totals row.
            $rows->push([
                $m['month'] . ' TOTAL',
                '', '', '',
                'PayPal/Card ' . number_format($m['paypal_total'], 2) . ' | BACS ' . number_format($m['bacs_total'], 2),
                'Not yet paid ' . number_format($m['notyetpaid_total'], 2),
                number_format($m['total'], 2),
            ]);
            $rows->push(['', '', '', '', '', '', '']);
        }

        if ($rows->isEmpty()) {
            $rows->push(['No sales found for the selected range.', '', '', '', '', '', '']);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Month', 'Invoice #', 'Date', 'Customer', 'Method', 'Status', 'Amount (ex-VAT)'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
