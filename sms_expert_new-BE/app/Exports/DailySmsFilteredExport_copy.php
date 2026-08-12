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

class DailySmsFilteredExport implements FromCollection, WithHeadings, WithStyles
{
    protected $dateFrom;
    protected $dateTo;
    protected $customerIds;
    protected $search;

    public function __construct($dateFrom = '', $dateTo = '', $customerIds = [], $search = '')
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->customerIds = is_array($customerIds) ? $customerIds : [];
        $this->search = $search;
    }

    public function collection()
    {
        $data = [];
        $sn = 1;

        // Date range

        $fromDate = blank($this->dateFrom)
            ? Carbon::now()->subDays(90)->startOfDay()->format('YmdHis')
            : Carbon::parse($this->dateFrom)->startOfDay()->format('YmdHis');

        $toDate = blank($this->dateTo)
            ? Carbon::now()->endOfDay()->format('YmdHis')
            : Carbon::parse($this->dateTo)->endOfDay()->format('YmdHis');
        // $fromDate = empty($this->dateFrom)
        //     ? Carbon::today()->format('Ymd') . '000000'
        //     : Carbon::parse($this->dateFrom)->format('Ymd') . '000000';

        // $toDate = empty($this->dateTo)
        //     ? Carbon::today()->format('Ymd') . '235959'
        //     : Carbon::parse($this->dateTo)->format('Ymd') . '235959';


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
            return collect([]);
        }


        /*
    |--------------------------------------------------------------------------
    | 2. Build UNION ALL
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
    | 3. Single aggregation query (FASTEST)
    |--------------------------------------------------------------------------
    */
        $results = DB::select("
        SELECT
            userref,
            SUM(profit) AS theprofit,
            SUM(numparts) AS thevolume,
            SUM(costprice) AS thecostprice,
            SUM(userprice) AS theuserprice
        FROM ({$unionSql}) AS logs
        GROUP BY userref
    ");


        /*
    |--------------------------------------------------------------------------
    | 4. Same formatting logic (unchanged)
    |--------------------------------------------------------------------------
    */
        foreach ($results as $row) {

            $user = DB::table('users')->where('bigid', $row->userref)->first();
            if (!$user) continue;

            if (!empty($this->customerIds) && !in_array($user->id, $this->customerIds)) {
                continue;
            }


            // ✅ SEARCH FILTER (THIS IS WHERE IT BELONGS)
            if (!empty($this->search)) {

                $searchLower = strtolower($this->search);

                if (
                    strpos(strtolower(urldecode($user->busname ?? '')), $searchLower) === false &&
                    strpos(strtolower(urldecode($user->contactname ?? '')), $searchLower) === false &&
                    strpos(strtolower($user->uname ?? ''), $searchLower) === false
                ) {
                    continue;
                }
            }


            $contactname = urldecode($user->busname ?? '');

            $costPrice = floatval($row->thecostprice);
            $userPrice = floatval($row->theuserprice);
            $profit    = floatval($row->theprofit);
            $volume    = floatval($row->thevolume);

            if ($profit == 0) {
                $profit = $userPrice - $costPrice;
            }

            $data[] = [
                $sn++,
                $contactname,
                urldecode($user->contactname ?? ''),
                $user->uname,
                number_format($volume),

                number_format($costPrice, 4, '.', ''),
                number_format($userPrice, 4, '.', ''),
                number_format($profit, 4, '.', ''),
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
            $dateRange = 'Last 90 Days';
            // $dateRange = Carbon::today()->format('d M Y');
        }

        return [
            ['Daily SMS Report', '', '', '', '', '', '', ''],
            ['Date Range: ' . $dateRange, '', '', '', '', '', '', ''],
            ['Generated: ' . now()->format('d M Y H:i:s'), '', '', '', '', '', '', ''],
            [],
            ['Sl.No', 'Customer Name', 'Contact Name', 'Username', 'Total SMS', 'Cost Price (£)', 'User Price (£)', 'Profit (£)'],
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
        ]);

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
