<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CustomerFeature;

/**
 * Customer Feature Seeder - OLD SYSTEM Data Migration
 *
 * Seeds customer-specific features from OLD SYSTEM hardcoded conditions.
 * Source files: smssend.inc, cp2_sendsms.inc, cp2_sendsms_process.inc, sms.mes
 */
class CustomerFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            // ==========================================
            // UTF-8 Decode Customers (from smssend.inc lines 832-844)
            // ==========================================
            [
                'user_bigid' => '4cc86cb3c9dc298d4a8eaqbb7de64225',
                'notes' => 'Anthony Burgin - Leadbyte',
                'utf8_decode' => true,
            ],
            [
                'user_bigid' => '12ac51b99ab8685a3beae3c3d39c28ad',
                'notes' => 'Colin McGeachie/Faretext (account: Papersky)',
                'utf8_decode' => true,
            ],
            [
                'user_bigid' => '844baaae8d2339c95397x537wffb395e',
                'notes' => 'Ian Johnson (account 1)',
                'utf8_decode' => true,
            ],
            [
                'user_bigid' => '8addfe6cd92749fc3d58f8aa4d633386',
                'notes' => 'Ian Johnson (account 2)',
                'utf8_decode' => true,
            ],
            [
                'user_bigid' => '5b08cdd671aab9fc83e42f00c732f4bd',
                'notes' => 'Ben Lambert - DMLS',
                'utf8_decode' => true,
            ],
            [
                'user_bigid' => 'c476bd3a2a8c238ebb8f273c2a9a9049',
                'notes' => 'Andrew Scott - Meteor',
                'utf8_decode' => true,
            ],
            [
                'user_bigid' => '42fb42646e9e8011f2df1b29cb7de709',
                'notes' => 'David Lynes - main master account',
                'utf8_decode' => true,
            ],
            [
                'user_bigid' => '59d0b3729ccff5085835245b901c1599',
                'notes' => 'David Lynes - sub-account',
                'utf8_decode' => true,
            ],
            [
                'user_bigid' => '65f050e205dff82f529eae1c6c133bb9',
                'notes' => 'steve19052 - testing',
                'utf8_decode' => true,
            ],

            // ==========================================
            // Lewis Black - Master Username (applies to all sub-accounts)
            // ==========================================
            [
                'user_bigid' => 'lewis_black_master',  // Placeholder - update with actual bigid
                'master_username' => 'bd87d9f6',
                'notes' => 'Lewis Black and all sub-accounts - UTF-8 decode via master username',
                'utf8_decode' => true,
            ],

            // ==========================================
            // Chris Sebire - Priority Queue + UTF-8 (from smssend.inc lines 838, 1057-1060)
            // ==========================================
            [
                'user_bigid' => '88fxfcfp9332decbff5b925fb895ark5',
                'notes' => 'Chris Sebire - whitelabel sub-account. UTF-8 + Priority queue for route "p"',
                'utf8_decode' => true,
                'priority_queue' => true,
                'priority_daemon_id' => 100,
                'priority_route' => 'p',
            ],
            [
                'user_bigid' => '33ja53a9a3cdc94qab5dea5dy7ecfd52',
                'notes' => 'Chris Sebire - Priority queue for route "p"',
                'priority_queue' => true,
                'priority_daemon_id' => 100,
                'priority_route' => 'p',
            ],

            // ==========================================
            // Steve's Test Accounts - Route Override + Debug (from smssend.inc lines 2108-2124, cp2_sendsms.inc line 399)
            // ==========================================
            [
                'user_bigid' => '73419c0c137c96c84a4490545e731838',
                'notes' => 'Steve test account - Route override + Debug mode',
                'route_override' => true,
                'debug_mode' => true,
            ],
            [
                'user_bigid' => '7e58b66442fc7b25bd29d6ee45440590',
                'notes' => 'Steve test account 2 - Route override',
                'route_override' => true,
            ],
            [
                'user_bigid' => '6641b01402fe76dd6656c16bc9c38700',
                'notes' => 'Steve test account 3 - Route override',
                'route_override' => true,
            ],

            // ==========================================
            // Test Mode Account (from cp2_sendsms_process.inc lines 66-79)
            // ==========================================
            [
                'user_bigid' => '91acce6a407daf3e1f0eb04f70349b7f',
                'notes' => 'Test account - Skip actual SMS sending, insert to buffer only',
                'test_mode' => true,
                'debug_mode' => true,
            ],

            // ==========================================
            // Route Fix - Brillchris (from sms.mes lines 91-103)
            // ==========================================
            [
                'user_bigid' => 'brillchris_bigid',  // Placeholder - update with actual bigid
                'username' => 'Brillchris',
                'notes' => 'Brillchris - Auto-fix route 8 to 7 with email notification',
                'route_fix_enabled' => true,
                'route_fix_from' => '8',
                'route_fix_to' => '7',
                'route_fix_notify' => true,
                'route_fix_notify_email' => env('SUPPORT_EMAIL', 'anand@nedholdings.com') . ',chris@activideas.com',
            ],
        ];

        foreach ($features as $feature) {
            CustomerFeature::updateOrCreate(
                ['user_bigid' => $feature['user_bigid']],
                array_merge([
                    'is_active' => true,
                    'utf8_decode' => false,
                    'priority_queue' => false,
                    'route_override' => false,
                    'debug_mode' => false,
                    'test_mode' => false,
                    'route_fix_enabled' => false,
                    'route_fix_notify' => false,
                ], $feature)
            );
        }

        $this->command->info('Customer features seeded successfully!');
        $this->command->info('Total records: ' . count($features));
        $this->command->warn('Note: Update placeholder bigids for Lewis Black and Brillchris with actual values.');
    }
}
