<?php

namespace App\Imports;

use App\Models\Country;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\Log;

class VonageCountryCostImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    protected $exchangeRate;
    protected $updatedCount = 0;
    protected $errors = [];

    public function __construct()
    {
        $this->exchangeRate = Country::whereNotNull('exchange_rate_eur_to_gbp')
            ->where('exchange_rate_eur_to_gbp', '>', 0)
            ->orderBy('exchange_rate_updated_at', 'desc')
            ->value('exchange_rate_eur_to_gbp') ?? 0.85;
    }

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

        // Get EUR cost value (column 4 after ISO, Country Name, Dial Code)
        $costEUR = $this->parseNumber($row['cost_price_eur'] ?? $row[3] ?? null);

        // Skip if EUR is empty or zero
        if ($costEUR <= 0) {
            return null;
        }

        // Auto-calculate GBP from EUR
        $costGBP = round($costEUR * $this->exchangeRate, 6);

        // Update country (set manual mode to prevent API override)
        $country->update([
            'cost_price_eur' => $costEUR,
            'cost_price_gbp' => $costGBP,
            'cost_per_sms' => $costEUR,
            'cost_price' => $costEUR,
            'price_update_mode' => 'manual',
        ]);

        $this->updatedCount++;

        Log::info('Vonage country cost imported', [
            'country' => $country->name,
            'iso_code' => $isoCode,
            'cost_eur' => $costEUR,
            'cost_gbp' => $costGBP,
            'exchange_rate' => $this->exchangeRate,
        ]);

        return null; // We're updating, not creating
    }

    protected function parseNumber($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }
        // Remove any non-numeric characters except decimal point
        $value = preg_replace('/[^0-9.]/', '', $value);
        return floatval($value);
    }

    public function rules(): array
    {
        return [
            'iso_code' => 'nullable|string|max:5',
            '0' => 'nullable|string|max:5', // Fallback for first column
        ];
    }

    public function headingRow(): int
    {
        return 5; // Headers are on row 5 after title rows
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
