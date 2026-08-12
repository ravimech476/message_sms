<?php

namespace App\Imports;

use App\Models\Country;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\Log;

class SinchCostImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    protected $updatedCount = 0;
    protected $errors = [];

    public function model(array $row)
    {
        // Get ISO code from first column
        $isoCode = strtoupper(trim($row['iso_code'] ?? $row[0] ?? ''));

        if (empty($isoCode)) {
            return null;
        }

        // Find country
        $country = Country::where('iso_code', $isoCode)->first();

        if (!$country) {
            $this->errors[] = "Country not found: {$isoCode}";
            return null;
        }

        // Get GBP cost value (column 4 after ISO, Country Name, Dial Code)
        $costGBP = $this->parseNumber($row['sinch_cost_gbp'] ?? $row['sinch_cost_price_gbp'] ?? $row[3] ?? null);

        // Skip if GBP is empty or zero
        if ($costGBP <= 0) {
            return null;
        }

        // Update country with Sinch GBP price only
        $country->update([
            'sinch_cost_price_gbp' => $costGBP,
            'sinch_price_updated_at' => now(),
        ]);

        $this->updatedCount++;

        Log::info('Sinch country cost imported', [
            'country' => $country->name,
            'iso_code' => $isoCode,
            'sinch_cost_gbp' => $costGBP,
        ]);

        return null;
    }

    protected function parseNumber($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $value = preg_replace('/[^0-9.]/', '', $value);
        return floatval($value);
    }

    public function rules(): array
    {
        return [
            'iso_code' => 'nullable|string|max:5',
            '0' => 'nullable|string|max:5',
        ];
    }

    public function headingRow(): int
    {
        return 4; // Headers are on row 4 after title rows
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
