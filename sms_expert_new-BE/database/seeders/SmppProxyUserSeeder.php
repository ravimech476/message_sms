<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SmppProxyUser;

/**
 * SMPP Proxy User Seeder - OLD SYSTEM Data Migration
 *
 * Seeds SMPP proxy user configurations from OLD SYSTEM hardcoded conditions.
 * Source file: smssend.inc lines 270-397
 */
class SmppProxyUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $proxyUsers = [
            // ==========================================
            // Craig Rutherford - SMPP Front End Provider
            // From smssend.inc lines 270-397
            // ==========================================
            [
                'proxy_username' => '6c3d9bb6',
                'proxy_password' => '8e43dab0',
                'proxy_name' => 'Craig Rutherford',
                'allowed_ips' => ['54.247.190.133', '79.125.18.135'],
                'notify_email' => env('SUPPORT_EMAIL', 'anand@nedholdings.com'),
                'is_active' => true,
                'notes' => 'Craig Rutherford provides an SMPP front end. ' .
                    'He authenticates with his credentials and passes smppusr param ' .
                    'to represent the actual user within his system. ' .
                    'Source: smssend.inc lines 270-397',
            ],
        ];

        foreach ($proxyUsers as $proxyUser) {
            SmppProxyUser::updateOrCreate(
                ['proxy_username' => $proxyUser['proxy_username']],
                $proxyUser
            );
        }

        $this->command->info('SMPP Proxy users seeded successfully!');
        $this->command->info('Total records: ' . count($proxyUsers));
        $this->command->newLine();
        $this->command->info('Craig Rutherford SMPP Proxy Details:');
        $this->command->info('  Username: 6c3d9bb6');
        $this->command->info('  Allowed IPs: 54.247.190.133, 79.125.18.135');
        $this->command->info('  Usage: usr=6c3d9bb6&pwd=8e43dab0&smppusr={ACTUAL_USER}');
    }
}
