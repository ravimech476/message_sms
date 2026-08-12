<?php

namespace App\Exports;

use App\Models\Country;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SinchCostExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithMapping
{
    public function collection()
    {
        return Country::select('id', 'iso_code', 'name', 'dialcode', 'sinch_cost_price_gbp', 'sinch_price_updated_at')
            ->orderBy('name')
            ->get();
    }

    public function map($country): array
    {
        return [
            $country->iso_code,
            $country->name,
            $country->dialcode,
            $country->sinch_cost_price_gbp ? number_format($country->sinch_cost_price_gbp, 6) : '',
            $country->sinch_price_updated_at ? \Carbon\Carbon::parse($country->sinch_price_updated_at)->format('Y-m-d H:i:s') : '',
        ];
    }

    public function headings(): array
    {
        return [
            ['Sinch Country Costs Export - ' . now()->format('d M Y H:i')],
            [],
            ['ISO Code', 'Country Name', 'Dial Code', 'Sinch Cost (GBP)', 'Last Updated'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge title row
        $sheet->mergeCells('A1:E1');

        // Title style
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7c3aed']],  // Purple for Sinch
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header row style (row 3)
        $sheet->getStyle('A3:E3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4E5A7A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // ISO Code
            'B' => 30,  // Country Name
            'C' => 12,  // Dial Code
            'D' => 18,  // Sinch Cost GBP
            'E' => 20,  // Last Updated
        ];
    }
}
