<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SinchCostTemplateExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection()
    {
        // Sample rows to show expected format
        return collect([
            ['GB', 'United Kingdom', '44', '0.016500'],
            ['US', 'United States', '1', '0.008500'],
            ['DE', 'Germany', '49', '0.066900'],
        ]);
    }

    public function headings(): array
    {
        return [
            ['Sinch Country Cost Upload Template'],
            ['Instructions: Fill in the Sinch cost prices in GBP. ISO Code must match existing countries in the database.'],
            [],
            ['ISO Code', 'Country Name (Info Only)', 'Dial Code (Info Only)', 'Sinch Cost (GBP)'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge title rows
        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('A2:D2');

        // Title style
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7c3aed']],  // Purple for Sinch
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Instructions style
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header row style (row 4)
        $sheet->getStyle('A4:D4')->applyFromArray([
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
            'C' => 15,  // Dial Code
            'D' => 18,  // Sinch Cost GBP
        ];
    }
}
