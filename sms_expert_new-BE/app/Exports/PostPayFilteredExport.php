<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PostPayFilteredExport implements FromCollection, WithHeadings, WithStyles
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

    // public function collection()
    // {
    //     $data = [];
    //     $sn = 1;

    //     $now = Carbon::now();

    //     // Set date range
    //     if (empty($this->dateFrom)) {
    //         $fromDate = $now->copy()->subMonths(6)->startOfMonth()->format('Ymd') . '000000';
    //     } else {
    //         $fromDate = Carbon::parse($this->dateFrom)->format('Ymd') . '000000';
    //     }

    //     if (empty($this->dateTo)) {
    //         $toDate = $now->format('Ymd') . '235959';
    //     } else {
    //         $toDate = Carbon::parse($this->dateTo)->format('Ymd') . '235959';
    //     }

    //     // Build user query
    //     $userQuery = DB::table('users')->where('customer_type', 'Postpaid');

    //     if (!empty($this->customerIds)) {
    //         $userQuery->whereIn('id', $this->customerIds);
    //     }

    //     $users = $userQuery->get();

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
    //             $sn++,
    //             urldecode($user->busname),
    //             urldecode($user->contactname),
    //             $user->uname,
    //             $numparts,
    //             number_format(floatval($userPrice), 2),
    //             number_format($totalCost, 2),
    //         ];
    //     }

    //     return collect($data);
    // }

    public function collection()
    {
        $data = [];
        $sn = 1;

        $now = Carbon::now();

        // Date range
        if (empty($this->dateFrom)) {
            $fromDate = $now->copy()->subMonths(6)->startOfMonth()->format('Ymd') . '000000';
        } else {
            $fromDate = Carbon::parse($this->dateFrom)->format('Ymd') . '000000';
        }

        if (empty($this->dateTo)) {
            $toDate = $now->format('Ymd') . '235959';
        } else {
            $toDate = Carbon::parse($this->dateTo)->format('Ymd') . '235959';
        }


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


        /*
    |--------------------------------------------------------------------------
    | 2. Build UNION ALL once (FAST)
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

        $smsTotals = collect(DB::select("
        SELECT userref, SUM(numparts) AS total_parts
        FROM ({$unionSql}) AS logs
        GROUP BY userref
    "))->keyBy('userref');


        /*
    |--------------------------------------------------------------------------
    | 3. SAME USER QUERY (unchanged)
    |--------------------------------------------------------------------------
    */
        $userQuery = DB::table('users')->where('customer_type', 'Postpaid');

        if (!empty($this->customerIds)) {
            $userQuery->whereIn('id', $this->customerIds);
        }

        $users = $userQuery->get();


        /*
    |--------------------------------------------------------------------------
    | 4. SAME LOOP LOGIC
    |--------------------------------------------------------------------------
    */
        foreach ($users as $user) {

            $userRef = $user->bigid;

            // replaced single table sum with UNION result
            $numparts = intval($smsTotals[$userRef]->total_parts ?? 0);

            $userPrice = DB::table('smsg_userroute')
                ->where('userref', $userRef)
                ->orderByDesc('id')
                ->value('userprice') ?? '0.06';

            $totalCost = $numparts * floatval($userPrice);

            $data[] = [
                $sn++,
                urldecode($user->busname),
                urldecode($user->contactname),
                $user->uname,
                $numparts,
                number_format(floatval($userPrice), 2),
                number_format($totalCost, 2),
            ];
        }

        return collect($data);
    }


    public function headings(): array
    {
        $dateRange = '';
        if (!empty($this->dateFrom) && !empty($this->dateTo)) {
            $dateRange = Carbon::parse($this->dateFrom)->format('d M Y') . ' - ' . Carbon::parse($this->dateTo)->format('d M Y');
        } else {
            $dateRange = 'Last 6 Months';
        }

        return [
            ['Post-Pay Customer Report', '', '', '', '', '', ''],
            ['Date Range: ' . $dateRange, '', '', '', '', '', ''],
            ['Generated: ' . now()->format('d M Y H:i:s'), '', '', '', '', '', ''],
            [],
            ['Sl.No', 'Customer Name', 'Contact Name', 'Username', 'Total SMS', 'Per SMS Rate', 'Total Cost (£)'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Title styles
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->mergeCells('A3:G3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
        ]);

        // Header row styles
        $sheet->getStyle('A5:G5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4E5A7A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
