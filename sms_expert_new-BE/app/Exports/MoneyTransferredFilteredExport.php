<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class MoneyTransferredFilteredExport implements FromCollection, WithHeadings, WithStyles
{
    protected $dateFrom;
    protected $dateTo;
    protected $customerIds;

    public function __construct($dateFrom = '', $dateTo = '', $customerIds = [])
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->customerIds = is_array($customerIds) ? $customerIds : [];
    }

    public function collection(): Collection
    {
        $data = collect();

        // Set date range
        if (empty($this->dateFrom)) {
            $from = Carbon::now()->subDays(90)->startOfDay()->toDateTimeString();
        } else {
            $from = Carbon::parse($this->dateFrom)->startOfDay()->toDateTimeString();
        }
        
        if (empty($this->dateTo)) {
            $to = Carbon::now()->endOfDay()->toDateTimeString();
        } else {
            $to = Carbon::parse($this->dateTo)->endOfDay()->toDateTimeString();
        }

        $logs = DB::table('money_transfer_logs')
            ->where('status', 1)
            ->whereBetween('created', [$from, $to])
            ->orderByDesc('created')
            ->get();

        $sn = 1;
        foreach ($logs as $log) {
            $fromAccount = DB::table('users')->where('uname', $log->from_account)->first();
            $toAccount = DB::table('users')->where('uname', $log->to_account)->first();
            $mainAccount = DB::table('users')->where('uname', $fromAccount->masteruname ?? '')->first();

            // Apply customer filter (multiple customers)
            if (!empty($this->customerIds)) {
                $fromMatch = $fromAccount && in_array($fromAccount->id, $this->customerIds);
                $toMatch = $toAccount && in_array($toAccount->id, $this->customerIds);
                if (!$fromMatch && !$toMatch) {
                    continue;
                }
            }

            $data->push([
                $sn++,
                date("d M Y H:i:s", strtotime($log->created)),
                number_format($log->amount, 2),
                urldecode($mainAccount->busname ?? ''),
                urldecode($fromAccount->busname ?? ''),
                urldecode($toAccount->busname ?? ''),
                $log->created_by,
                $log->ip_address,
            ]);
        }

        return $data;
    }

    public function headings(): array
    {
        $dateRange = '';
        if (!empty($this->dateFrom) && !empty($this->dateTo)) {
            $dateRange = Carbon::parse($this->dateFrom)->format('d M Y') . ' - ' . Carbon::parse($this->dateTo)->format('d M Y');
        } else {
            $dateRange = 'Last 90 Days';
        }

        return [
            ['Money Transferred Report', '', '', '', '', '', '', ''],
            ['Date Range: ' . $dateRange, '', '', '', '', '', '', ''],
            ['Generated: ' . now()->format('d M Y H:i:s'), '', '', '', '', '', '', ''],
            [],
            ['Sl.No', 'Date/Time', 'Amount (£)', 'Lead Account', 'Transferred From', 'Transferred To', 'Transferred By', 'IP Address'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Title styles
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');
        $sheet->mergeCells('A3:H3');
        
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
        ]);

        // Header row styles
        $sheet->getStyle('A5:H5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4E5A7A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => 'thin']],
        ]);

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
