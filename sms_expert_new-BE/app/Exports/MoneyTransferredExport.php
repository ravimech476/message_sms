<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MoneyTransferredExport implements FromCollection, WithHeadings, WithStyles
{
    public function collection(): Collection
    {
        $data = collect();

        for ($i = 0; $i <= 90; $i++) {
            $date = now()->subDays($i);
            $from = $date->startOfDay()->toDateTimeString();
            $to = $date->endOfDay()->toDateTimeString();

            $logs = DB::table('money_transfer_logs')
                ->where('status', 1)
                ->whereBetween('created', [$from, $to])
                ->get();

            foreach ($logs as $log) {
                $fromAccount = DB::table('users')->where('uname', $log->from_account)->first();
                $toAccount = DB::table('users')->where('uname', $log->to_account)->first();
                $mainAccount = DB::table('users')->where('uname', $fromAccount->masteruname ?? '')->first();

                $data->push([
                    date("jS M Y H:i:s a", strtotime($log->created)),
                    $log->amount,
                    urldecode($mainAccount->busname ?? ''),
                    urldecode($fromAccount->busname ?? ''),
                    urldecode($toAccount->busname ?? ''),
                    $log->created_by,
                    $log->ip_address,
                ]);
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Date/Time',
            'Amount Transferred',
            'Lead Account',
            'Transferred From',
            'Transferred To',
            'Transferred By',
            'IP Address',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4E5A7A']],
            'alignment' => ['horizontal' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin']],
        ]);
    }
}

