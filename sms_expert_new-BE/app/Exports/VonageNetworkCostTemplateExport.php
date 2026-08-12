<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class VonageNetworkCostTemplateExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection()
    {
        // Sample rows to show expected format
        return collect([
            ['GB', 'United Kingdom', '23410', 'O2', '0.015000', '0.016500'],
            ['GB', 'United Kingdom', '23415', 'Vodafone', '0.017500', '0.019000'],
            ['GB', 'United Kingdom', '23420', 'Hutchison 3G', '0.016000', '0.017800'],
            ['US', 'United States', '310260', 'T-Mobile', '0.008000', '0.009000'],
        ]);
    }

    public function headings(): array
    {
        return [
            ['Vonage Network Cost Upload Template'],
            ['Instructions: Fill in the network-specific cost prices. ISO Code must match existing countries.'],
            ['Network Code is MCC+MNC (e.g., 23410 for O2 UK). If network exists, it will be updated; otherwise created.'],
            [],
            ['ISO Code', 'Country Name (Info Only)', 'Network Code (MCC+MNC)', 'Network Name', 'Cost Price (GBP)', 'Cost Price (EUR)'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge title rows
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');
        $sheet->mergeCells('A3:F3');

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
        $sheet->getStyle('A5:F5')->applyFromArray([
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
            'B' => 25,  // Country Name
            'C' => 20,  // Network Code
            'D' => 20,  // Network Name
            'E' => 18,  // Cost GBP
            'F' => 18,  // Cost EUR
        ];
    }
}
