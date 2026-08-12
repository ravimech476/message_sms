<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class DailySmsReportExport implements FromCollection, WithHeadings, WithStyles, WithEvents
{
    protected $reportDate;

    public function __construct()
    {
        $this->reportDate = Carbon::yesterday()->format('jS M');
    }

    public function collection()
    {
        $data = [];
        $sn = 1;

        $fromDate = Carbon::yesterday()->format('Ymd') . '000000';
        $toDate = Carbon::yesterday()->format('Ymd') . '235959';

        // $fromDateDecrypt = Carbon::createFromFormat('YmdHis', $fromDate);
        // $toDateDecrypt = Carbon::createFromFormat('YmdHis', $toDate);
        // echo $fromDateDecrypt->format('Y-m-d H:i:s');
        // echo $toDateDecrypt->format('Y-m-d H:i:s');
        // exit; 

        $userRefs = DB::table('smsg_log')
            ->select('userref')
            ->whereBetween('timesent', [$fromDate, $toDate])
            ->where('sentstatus', 'ok')
            ->distinct()
            ->pluck('userref');

        foreach ($userRefs as $userref) {
            $user = DB::table('users')->where('bigid', $userref)->first();
            if (!$user) continue;

            $contactname = urldecode($user->busname ?? '');

            $metrics = DB::table('smsg_log')
                ->selectRaw('
                    SUM(profit - hlrlookupcost) AS theprofit,
                    SUM(numparts) AS thevolume,
                    SUM(costprice) AS thecostprice,
                    SUM(userprice) AS theuserprice
                ')
                ->where('userref', $userref)
                ->whereBetween('timesent', [$fromDate, $toDate])
                ->where('sentstatus', 'ok')
                ->first();

            $data[] = [
                $sn++,
                $contactname,
                number_format($metrics->thevolume ?? 0),
                number_format($metrics->thecostprice ?? 0, 2),
                number_format($metrics->theuserprice ?? 0, 2),
                number_format($metrics->theprofit ?? 0, 2),
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            ['Report Date', $this->reportDate],
            [],
            ['Sl.No', 'Customer Name', 'Total SMS', 'Cost Price', 'User Price', 'Profit'], // Main table headings
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            3 => [ // Table headings on row 3 (A3)
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '4E5A7A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            1 => [ // Report date on row 1 (A1)
                'font' => [
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ],
        ];
    }


    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Merge A1:F1 for "Report Date"
                $event->sheet->mergeCells('A1');
                $event->sheet->freezePane('A4');
            },
        ];
    }
}
