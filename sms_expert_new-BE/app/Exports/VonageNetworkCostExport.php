<?php

namespace App\Exports;

use App\Models\VonageNetworkCost;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class VonageNetworkCostExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithMapping
{
    public function collection()
    {
        return VonageNetworkCost::orderBy('country_name')
            ->orderBy('network_name')
            ->get();
    }

    public function map($network): array
    {
        return [
            $network->country_code,
            $network->country_name,
            $network->network_code ?? '',
            $network->network_name,
            $network->cost_price_gbp ? number_format($network->cost_price_gbp, 6) : '',
            $network->cost_price_eur ? number_format($network->cost_price_eur, 6) : '',
            $network->is_active ? 'Yes' : 'No',
            $network->updated_at ? $network->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }

    public function headings(): array
    {
        return [
            ['Vonage Network Costs Export - ' . now()->format('d M Y H:i')],
            [],
            ['ISO Code', 'Country Name', 'Network Code', 'Network Name', 'Cost Price (GBP)', 'Cost Price (EUR)', 'Active', 'Last Updated'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge title row
        $sheet->mergeCells('A1:H1');

        // Title style
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ea6118']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header row style (row 3)
        $sheet->getStyle('A3:H3')->applyFromArray([
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
            'C' => 15,  // Network Code
            'D' => 20,  // Network Name
            'E' => 18,  // Cost GBP
            'F' => 18,  // Cost EUR
            'G' => 10,  // Active
            'H' => 20,  // Last Updated
        ];
    }
}
