<?php

namespace App\Imports;

use App\Models\Country;
use App\Models\VonageNetworkCost;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\Log;

class VonageNetworkCostImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    protected $exchangeRate;
    protected $updatedCount = 0;
    protected $createdCount = 0;
    protected $errors = [];
    protected $countryCache = [];

    public function __construct()
    {
        $this->exchangeRate = Country::whereNotNull('exchange_rate_eur_to_gbp')
            ->where('exchange_rate_eur_to_gbp', '>', 0)
            ->orderBy('updated_at', 'desc')
            ->value('exchange_rate_eur_to_gbp') ?? 0.85;
    }

    public function model(array $row)
    {
        // Get values from row
        $isoCode = strtoupper(trim($row['iso_code'] ?? $row[0] ?? ''));
        $networkCode = trim($row['network_code_mccmnc'] ?? $row['network_code'] ?? $row[2] ?? '');
        $networkName = trim($row['network_name'] ?? $row[3] ?? '');

        if (empty($isoCode) || empty($networkName)) {
            return null;
        }

        // Find country (with caching)
        if (!isset($this->countryCache[$isoCode])) {
            $country = Country::where('iso_code', $isoCode)->first();
            $this->countryCache[$isoCode] = $country;
        }
        $country = $this->countryCache[$isoCode];

        if (!$country) {
            $this->errors[] = "Country not found: {$isoCode}";
            return null;
        }

        // Get cost values
        $costGBP = $this->parseNumber($row['cost_price_gbp'] ?? $row[4] ?? null);
        $costEUR = $this->parseNumber($row['cost_price_eur'] ?? $row[5] ?? null);

        // Calculate missing currency
        if ($costGBP > 0 && $costEUR <= 0) {
            $costEUR = round($costGBP / $this->exchangeRate, 6);
        } elseif ($costEUR > 0 && $costGBP <= 0) {
            $costGBP = round($costEUR * $this->exchangeRate, 6);
        }

        // Find existing network or create new
        $network = VonageNetworkCost::where('country_code', $isoCode)
            ->where(function ($query) use ($networkCode, $networkName) {
                if (!empty($networkCode)) {
                    $query->where('network_code', $networkCode);
                } else {
                    $query->where('network_name', $networkName);
                }
            })
            ->first();

        if ($network) {
            // Update existing
            $network->update([
                'network_name' => $networkName,
                'cost_price_gbp' => $costGBP,
                'cost_price_eur' => $costEUR,
            ]);
            $this->updatedCount++;
        } else {
            // Create new
            VonageNetworkCost::create([
                'country_id' => $country->id,
                'country_code' => $isoCode,
                'country_name' => $country->name,
                'network_code' => $networkCode ?: null,
                'network_name' => $networkName,
                'cost_price_gbp' => $costGBP,
                'cost_price_eur' => $costEUR,
                'is_active' => true,
            ]);
            $this->createdCount++;
        }

        Log::info('Vonage network cost imported', [
            'country' => $country->name,
            'network' => $networkName,
            'network_code' => $networkCode,
            'action' => $network ? 'updated' : 'created',
            'cost_gbp' => $costGBP,
            'cost_eur' => $costEUR,
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
        return 5;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
