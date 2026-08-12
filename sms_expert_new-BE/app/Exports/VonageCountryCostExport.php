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

class VonageCountryCostExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithMapping
{
    public function collection()
    {
        return Country::select('id', 'iso_code', 'name', 'dialcode', 'cost_price_eur', 'cost_price_gbp', 'updated_at')
            ->orderBy('name')
            ->get();
    }

    public function map($country): array
    {
        return [
            $country->iso_code,
            $country->name,
            $country->dialcode,
            $country->cost_price_eur ? number_format($country->cost_price_eur, 6) : '',
            $country->cost_price_gbp ? number_format($country->cost_price_gbp, 6) : '',
            $country->updated_at ? $country->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }

    public function headings(): array
    {
        return [
            ['Vonage Country Costs Export - ' . now()->format('d M Y H:i')],
            [],
            ['ISO Code', 'Country Name', 'Dial Code', 'Cost Price (EUR)', 'Cost Price (GBP)', 'Last Updated'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge title row
        $sheet->mergeCells('A1:F1');

        // Title style
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ea6118']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header row style (row 3)
        $sheet->getStyle('A3:F3')->applyFromArray([
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
            'D' => 18,  // Cost EUR
            'E' => 18,  // Cost GBP
            'F' => 20,  // Last Updated
        ];
    }
}
