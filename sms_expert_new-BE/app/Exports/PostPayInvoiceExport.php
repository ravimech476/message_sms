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

class PostPayInvoiceExport implements FromCollection, WithHeadings , WithStyles
{
    public function collection()
    {
        $data = [];
        $sn = 1;

        $now = Carbon::now()->startOfMonth();
        for ($i = 0; $i < 6; $i++) {
            $fromDate = $now->copy()->subMonths($i)->format('Ymd') . '000000';
            $toDate = $now->copy()->subMonths($i)->endOfMonth()->format('Ymd') . '235959';
            $invoiceMonth = $now->copy()->subMonths($i)->format('F-Y');

            $users = DB::table('users')->where('customer_type', 'Postpaid')->get();

            foreach ($users as $user) {
                $userRef = $user->bigid;

                // Total SMS parts
                $numparts = DB::table('smsg_log')
                    ->where('userref', $userRef)
                    ->whereBetween('timesent', [$fromDate, $toDate])
                    ->where('sentstatus', 'ok')
                    ->sum('numparts');

                // Get SMS rate
                $userPrice = DB::table('smsg_userroute')
                    ->where('userref', $userRef)
                    ->orderByDesc('id')
                    ->value('userprice') ?? '0.06';

                $data[] = [
                    $sn++,
                    $invoiceMonth,
                    urldecode($user->busname),
                    urldecode($user->contactname),
                    $numparts,
                    $userPrice,
                ];
            }
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Sl.No',
            'Invoice Month',
            'Customer Name',
            'Username (lead contact)',
            'Total Number of SMS',
            'Per SMS Rate (Latest Price)',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
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
        ];
    }
}

