<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * XML Gateway Customer Seeder - OLD SYSTEM Data Migration
 *
 * Seeds customer-specific features from OLD SYSTEM hardcoded conditions.
 * Source file: incoming_itagg_xml.php, UserVisibility.class
 */
class XmlGatewayCustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ==========================================
        // Customer Features
        // ==========================================
        $customers = [
            // Arun Estates - Default route 7002, skip confirmations
            // Source: incoming_itagg_xml.php lines 279, 440
            [
                'user_bigid' => null, // Will be looked up from username
                'username' => 'arunestates',
                'default_route' => '7002',
                'skip_confirmation' => true,
                'skip_confirmation_emails' => null,
                'notes' => 'Arun Estates - Default route 7002 when not specified, skip all confirmations. Source: incoming_itagg_xml.php lines 279, 440',
                'is_active' => true,
            ],

            // Mark - Default route 7002
            // Source: incoming_itagg_xml.php line 280
            [
                'user_bigid' => null,
                'username' => 'mark',
                'default_route' => '7002',
                'skip_confirmation' => false,
                'skip_confirmation_emails' => null,
                'notes' => 'Mark (password: 2cowgreece) - Default route 7002 when not specified. Source: incoming_itagg_xml.php line 280',
                'is_active' => true,
            ],

            // Hardys and Hansons - Skip confirmations for specific emails
            // Source: incoming_itagg_xml.php lines 439-443
            [
                'user_bigid' => null,
                'username' => 'hardysandhansons',
                'default_route' => null,
                'skip_confirmation' => false,
                'skip_confirmation_emails' => json_encode([
                    'steve.bemrose@hardysandhansons.plc.uk',
                    'janice.harrison@hardysandhansons.plc.uk',
                    'shirley.dickinson@hardysandhansons.plc.uk',
                ]),
                'notes' => 'Hardys and Hansons - Skip confirmations for these specific emails. Source: incoming_itagg_xml.php lines 439-443',
                'is_active' => true,
            ],
        ];

        foreach ($customers as $customer) {
            // Try to find the actual bigid from username if not provided
            if (empty($customer['user_bigid']) && !empty($customer['username'])) {
                $user = DB::table('users')
                    ->where('uname', $customer['username'])
                    ->first();

                if ($user) {
                    $customer['user_bigid'] = $user->bigid;
                }
            }

            DB::table('xml_gateway_customers')->updateOrInsert(
                ['username' => $customer['username']],
                array_merge($customer, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // ==========================================
        // Shortcode Restrictions
        // ==========================================
        $shortcodeRestrictions = [
            // Shortcode 82958 - SpiralArm only
            // Source: UserVisibility.class line 201
            [
                'shortcode' => '82958',
                'allowed_bigid' => 'dcd735888fac7d724773f574e7d68191',
                'customer_name' => 'SpiralArm',
                'notes' => 'Only SpiralArm can use shortcode 82958. Source: UserVisibility.class line 201',
                'is_active' => true,
            ],

            // Shortcode 82466
            // Source: UserVisibility.class line 206
            [
                'shortcode' => '82466',
                'allowed_bigid' => '4eea19bc689a0631f19a1ed6f4c7279f',
                'customer_name' => 'Unknown - needs identification',
                'notes' => 'Restricted shortcode 82466. Source: UserVisibility.class line 206',
                'is_active' => true,
            ],
        ];

        foreach ($shortcodeRestrictions as $restriction) {
            DB::table('xml_gateway_shortcode_restrictions')->updateOrInsert(
                ['shortcode' => $restriction['shortcode']],
                array_merge($restriction, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('XML Gateway customers seeded successfully!');
        $this->command->info('Customers: ' . count($customers));
        $this->command->info('Shortcode restrictions: ' . count($shortcodeRestrictions));
        $this->command->newLine();
        $this->command->info('Customer Features:');
        $this->command->info('  - arunestates: Default route 7002, skip confirmations');
        $this->command->info('  - mark: Default route 7002');
        $this->command->info('  - hardysandhansons: Skip confirmations for 3 emails');
        $this->command->newLine();
        $this->command->info('Shortcode Restrictions:');
        $this->command->info('  - 82958: SpiralArm only');
        $this->command->info('  - 82466: Specific bigid only');
    }
}
