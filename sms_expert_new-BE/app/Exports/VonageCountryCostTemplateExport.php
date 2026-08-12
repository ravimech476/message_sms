<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class VonageCountryCostTemplateExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection()
    {
        // Sample rows to show expected format
        return collect([
            ['GB', 'United Kingdom', '44', '0.018000'],
            ['US', 'United States', '1', '0.009500'],
            ['DE', 'Germany', '49', '0.075000'],
        ]);
    }

    public function headings(): array
    {
        return [
            ['Vonage Country Cost Upload Template'],
            ['Instructions: Fill in the EUR cost prices. ISO Code must match existing countries in the database.'],
            ['GBP will be auto-calculated using the current exchange rate.'],
            [],
            ['ISO Code', 'Country Name (Info Only)', 'Dial Code (Info Only)', 'Cost Price (EUR)'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge title rows
        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('A2:D2');
        $sheet->mergeCells('A3:D3');

        // Title style
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ea6118']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Instructions style
        $sheet->getStyle('A2:A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header row style (row 5)
        $sheet->getStyle('A5:D5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4E5A7A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows alignment
        $sheet->getStyle('A6:D100')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // ISO Code
            'B' => 25,  // Country Name
            'C' => 15,  // Dial Code
            'D' => 18,  // Cost EUR
        ];
    }
}
