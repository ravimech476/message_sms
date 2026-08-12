<?php

namespace App\Console\Commands;

use App\Services\SinchService;
use Illuminate\Console\Command;

class TestSinchNumbersApi extends Command
{
    protected $signature = 'sinch:test-numbers {country=GB : Country code (e.g., GB, US)}';

    protected $description = 'Test Sinch Numbers API connection and configuration';

    public function handle()
    {
        $this->info('Testing Sinch Numbers API Configuration...');
        $this->newLine();

        // Check configuration
        $projectId = config('sinch.numbers_project_id');
        $keyId = config('sinch.numbers_key_id');
        $keySecret = config('sinch.numbers_key_secret');
        $baseUrl = config('sinch.numbers_base_url');

        $this->info('Configuration Check:');
        $this->table(
            ['Setting', 'Value', 'Status'],
            [
                ['SINCH_NUMBERS_PROJECT_ID', $projectId ? substr($projectId, 0, 20) . '...' : '(not set)', $projectId ? '✓' : '✗'],
                ['SINCH_NUMBERS_KEY_ID', $keyId ? substr($keyId, 0, 20) . '...' : '(not set)', $keyId ? '✓' : '✗'],
                ['SINCH_NUMBERS_KEY_SECRET', $keySecret ? '********' : '(not set)', $keySecret ? '✓' : '✗'],
                ['SINCH_NUMBERS_BASE_URL', $baseUrl ?: '(not set)', $baseUrl ? '✓' : '✗'],
            ]
        );

        if (empty($projectId) || empty($keyId) || empty($keySecret)) {
            $this->error('Sinch Numbers API credentials are not configured!');
            $this->newLine();
            $this->info('Please add these to your .env file:');
            $this->line('SINCH_NUMBERS_PROJECT_ID=your_project_id');
            $this->line('SINCH_NUMBERS_KEY_ID=your_key_id');
            $this->line('SINCH_NUMBERS_KEY_SECRET=your_key_secret');
            $this->line('SINCH_NUMBERS_BASE_URL=https://numbers.api.sinch.com');
            return 1;
        }

        $this->newLine();
        $this->info('Testing API Connection...');

        $country = $this->argument('country');
        $sinchService = app(SinchService::class);

        // Test 1: Search available numbers
        $this->info("Searching available numbers in {$country}...");

        $result = $sinchService->searchAvailableNumbers($country, 'MOBILE', ['SMS'], 5);

        if ($result === null) {
            $this->error('API call failed! Check the logs at storage/logs/laravel.log for details.');
            return 1;
        }

        if (empty($result['numbers'])) {
            $this->warn("No available numbers found for {$country}. This could be normal if no inventory.");
        } else {
            $this->info('Found ' . count($result['numbers']) . ' available numbers:');
            $tableData = [];
            foreach ($result['numbers'] as $num) {
                $tableData[] = [
                    $num['msisdn'] ?? 'N/A',
                    $num['country'] ?? 'N/A',
                    $num['type'] ?? 'N/A',
                    implode(', ', $num['features'] ?? []),
                    $num['cost'] ?? 'N/A',
                ];
            }
            $this->table(['Number', 'Country', 'Type', 'Features', 'Cost'], $tableData);
        }

        // Test 2: Get owned numbers
        $this->newLine();
        $this->info('Fetching your active/owned numbers...');

        $ownedResult = $sinchService->getOwnedNumbers(10);

        if ($ownedResult === null) {
            $this->warn('Could not fetch owned numbers. Check logs for details.');
        } elseif (empty($ownedResult['activeNumbers'])) {
            $this->info('No active numbers found in your Sinch account.');
        } else {
            $this->info('Found ' . count($ownedResult['activeNumbers']) . ' active numbers:');
            $tableData = [];
            foreach ($ownedResult['activeNumbers'] as $num) {
                $tableData[] = [
                    $num['phoneNumber'] ?? 'N/A',
                    $num['regionCode'] ?? 'N/A',
                    $num['type'] ?? 'N/A',
                ];
            }
            $this->table(['Number', 'Country', 'Type'], $tableData);
        }

        $this->newLine();
        $this->info('Sinch Numbers API test completed!');

        return 0;
    }
}
