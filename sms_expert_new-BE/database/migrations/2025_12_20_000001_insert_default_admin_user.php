<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add any missing columns to users table
        $this->addMissingColumns();

        // Check if admin user already exists
        $existingAdmin = DB::table('users')->where('uname', 'admin')->first();

        if ($existingAdmin) {
            // Admin already exists, skip insertion
            return;
        }

        // Generate unique bigid
        $bigid = Str::random(32);

        // Build insert data with only existing columns
        $userData = [
            'bigid' => $bigid,
            'oldewnthisuser' => '',
            'first_ip' => '103.58.116.235',
            'agencyref' => '0',
            'busname' => 'Admin',
            'uname' => 'admin',
            'pword' => '123456',
            'contactname' => 'Admin',
            'address1' => null,
            'address2' => null,
            'town' => null,
            'city' => null,
            'county' => null,
            'pcode' => null,
            'country' => '',
            'phone' => '9003096885',
            'fax' => null,
            'contactemail' => 'anand@nedholdings.com',
            'website' => null,
            'custdescr' => null,
            'datejoined' => time(),
            'datefrozen' => time() + 31536000,
            'howheardofus' => null,
            'notes' => null,
            'resellerref' => null,
            'transferallowed' => 'y',
            'tv_id' => null,
            'isinternalemail' => 'no',
            'getsnewsletter' => 'no',
            'sendhtml' => 'no',
            'mobilenumber' => '9003096885',
            'mobile_valid' => 'n',
            'mobnetworksref' => 0,
            'mobilecountryprefix' => '',
            'favfootballteam' => '',
            'keenonaffiliate' => '',
            'alerts' => 0,
            'hadbonus' => 'n',
            'add_sig' => 'y',
            'bulkroute' => 'bulksms',
            'mobile_modelref' => 0,
            'premwallet' => 0.00,
            'smsg_wallet' => 2.500000,
            'smsg_server1_sent' => 0.000000,
            'smsg_server2_sent' => 0.000000,
            'sms_sender_id' => 'MYBRANDNAME',
            'sms_default_route' => 7,
            'sentitems_itemsperpage' => 20,
            'mynumbers_itemsperpage' => 20,
            'premium_throughput' => 0,
            'premium_throughput_lastwarned' => null,
            'subdomain_restrict' => null,
            'bit_disabled' => 0,
            'user_type' => 'bespoke',
            'postcard_credits_wallet' => 0,
            'postcard_credits_used' => 0,
            'lead_generated' => 'itagg',
            'paypal_percent' => 75,
            'datacash_percent' => 75,
            'is_SMPP' => 'no',
            'do_ip_check' => 'yes',
            'firstname' => '',
            'platkeywordwallet' => 1.000000,
            'platkeywordcost' => 1.000000,
            'platinumaccess' => 'y',
            'platnextlowwarning' => '',
            'meetorchat' => '',
            'showcommissionsupto' => '200810',
            'bulk_throughput' => 0,
            'bulk_throughput_lastwarned' => null,
            'clientcommstatus' => 'cool',
            'bulksms_daily_tally' => 0,
            'bulksms_tally_last_reset' => '0000-00-00 00:00:00',
            'affiliateinvitecode' => 'EFE64',
            'emailverification' => '',
            'staffvetted' => 'no',
            'affiliatetype' => 'quick',
            'affiliatesmsearnedtotal' => 0.000000,
            'affiliatesmsearnedsincelastwithdraw' => 0.000000,
            'affiliatesignupcmsnpounds' => 0.000000000,
            'affiliateupgradecmsnpercent' => 0.000000000,
            'smsinprice' => 35.000000000,
            'silverprice' => 0.000000000,
            'goldprice' => 0.000000000,
            'platinumprice' => 0.000000000,
            'free100sms' => 'cant',
            '1s_preferredroute' => 7002,
            'hlrlookupcost' => 0.005000000,
            'daemonpriority' => 400,
            'dohlr' => 0,
            'dohlrtmp' => 'normal',
            'preferredroutetmp' => '0',
            'randomizeroute' => 0,
            'masteruname' => substr($bigid, 0, 8),
            'introducerref' => '',
            'userpause' => 'n',
            'userpausetmp' => 'n',
            'preferredroute2' => 0,
            'dohlr2' => 0,
            'randomizeroute2' => 0,
            'meetlocation' => '',
            'isnfcuser' => '',
            'nfckey' => 'parjmq',
            'sendhlrunknowns' => 'y',
            'sendhlrunknowns2' => 'y',
            'sendhlrunknowns3' => 'y',
            'preferredroute3' => 0,
            'dohlr3' => 0,
            'randomizeroute3' => 0,
            'routetag' => 'd',
            'routetag2' => '0',
            'routetag3' => '0',
            'preferredroutesplit' => 0,
            'dashboardaccess' => 'mc',
            'preferredroutesplit2' => 0,
            'itaggsecretkey' => '',
            'chargetype1' => 'pps',
            'chargetype2' => 'pps',
            'chargetype3' => 'pps',
            'trackwallet' => 'y',
            'badhlrlastwarned' => '',
            'spotcheckrisk' => 1000,
            'spotchecklastnotice' => '0',
            'oldplatkeywordwallet' => 0.000000,
            'currentsmppdate' => '0',
            'lastsmppdateroll' => 0,
            'smppsent1' => 0,
            'smppsent2' => 0,
            'smppsent3' => 0,
            'smppsent4' => 0,
            'smppsent5' => 0,
            'subusrkey' => null,
            'numpooledvirtsUK' => 0,
            'numpooledvirtsUSA' => 0,
            'walletminlevel' => 0.000000,
            'walletloan' => 0.000000,
            'shortdomain' => '',
            'lab2id' => 'itagg',
            'contactemail2' => null,
            'contractproperlyagreed' => null,
            'shopify_cust_id' => '',
            'anondesc' => '',
            'custtype' => '',
            'newrate' => 0.000000,
            'whatsapprate' => null,
            'whatsapp_enabled' => 'no',
            'customer_type' => null,
            'ip_address_restriction' => 2,
            '1forgotpassword' => 2,
            '1forgot_password_link' => 0,
            '1forgot_password_submit' => 0,
            '1forgot_password1_link' => 0,
            '1forgot_password1_submit' => 0,
            'logout_session' => 0,
            'forgotpassword' => 2,
            'forgot_password_link' => 0,
            'forgot_password_submit' => 0,
            'forgot_password1_link' => 0,
            'forgot_password1_submit' => 0,
            'remember_token' => null,
            'common_sms_rate' => null,
            'use_common_rate' => 0,
            'role' => 'admin',
            'admin_role_id' => 2,
            'last_login_at' => now(),
            'last_login_ip' => '127.0.0.1',
            'created_by' => null,
        ];

        // Add optional columns if they exist
        if (Schema::hasColumn('users', 'migration_flag')) {
            $userData['migration_flag'] = 'new';
        }
        if (Schema::hasColumn('users', 'login_type')) {
            $userData['login_type'] = 'admin';
        }
        if (Schema::hasColumn('users', 'status')) {
            $userData['status'] = 'active';
        }
        if (Schema::hasColumn('users', 'account_locked')) {
            $userData['account_locked'] = 0;
        }
        if (Schema::hasColumn('users', 'profile_edit_allowed')) {
            $userData['profile_edit_allowed'] = 1;
        }
        if (Schema::hasColumn('users', 'api_type')) {
            $userData['api_type'] = 'original';
        }
        if (Schema::hasColumn('users', 'ip_restriction_enabled')) {
            $userData['ip_restriction_enabled'] = 0;
        }
        if (Schema::hasColumn('users', 'security_settings_updated_at')) {
            $userData['security_settings_updated_at'] = null;
        }
        if (Schema::hasColumn('users', 'last_login')) {
            $userData['last_login'] = null;
        }

        // Insert admin user
        $userId = DB::table('users')->insertGetId($userData);

        // Check if admin_permissions table exists
        if (!Schema::hasTable('admin_permissions')) {
            return;
        }

        // Check if permission already exists for this user
        $existingPermission = DB::table('admin_permissions')->where('user_id', $userId)->first();

        if ($existingPermission) {
            return;
        }

        // Build permissions data with only existing columns
        $permissionsData = [
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // List of all possible permission columns
        $permissionColumns = [
            'can_view_dashboard',
            'can_view_customers',
            'can_view_customer_emails',
            'can_view_virtual_numbers',
            'can_view_reports',
            'can_view_settings',
            'can_manage_admin_users',
            'can_manage_contracts',
            'can_view_customer_smpp',
            'can_manage_customer_smpp',
            'can_create_smpp_accounts',
            'can_edit_smpp_accounts',
            'can_delete_smpp_accounts',
            'can_view_smpp_logs',
            'can_view_customer_profile',
            'can_edit_customer_profile',
            'can_view_customer_keywords',
            'can_edit_customer_keywords',
            'can_view_customer_virtual_numbers',
            'can_edit_customer_virtual_numbers',
            'can_view_customer_notes',
            'can_edit_customer_notes',
            'can_view_customer_wallet',
            'can_edit_customer_wallet',
            'can_view_customer_logs',
            'can_view_customer_reports',
            'can_create_customers',
            'can_delete_customers',
            'can_export_data',
            'can_view_system_status',
            'can_view_system_logs',
            'can_view_user_activity',
            'can_view_env_variables',
            'can_manage_cache',
            'can_view_process_monitor',
            'can_manage_customer_settings',
            'can_manage_general_customer_settings',
            'can_manage_global_maintenance',
            'can_manage_customer_maintenance',
            'can_manage_notifications',
            'can_view_postpay_report',
            'can_view_daily_sms_report',
            'can_view_money_transfer_report',
        ];

        // Add only existing permission columns
        foreach ($permissionColumns as $column) {
            if (Schema::hasColumn('admin_permissions', $column)) {
                $permissionsData[$column] = 1;
            }
        }

        // Insert admin permissions
        DB::table('admin_permissions')->insert($permissionsData);
    }

    /**
     * Add missing columns to users table
     */
    private function addMissingColumns(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'login_type')) {
                $table->string('login_type', 50)->nullable()->after('remember_token');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 50)->nullable()->default('active');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'account_locked')) {
                $table->tinyInteger('account_locked')->default(0);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_edit_allowed')) {
                $table->tinyInteger('profile_edit_allowed')->default(1);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'api_type')) {
                $table->string('api_type', 50)->nullable()->default('original');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'ip_restriction_enabled')) {
                $table->tinyInteger('ip_restriction_enabled')->default(0);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'security_settings_updated_at')) {
                $table->timestamp('security_settings_updated_at')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_login')) {
                $table->timestamp('last_login')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find the admin user
        $admin = DB::table('users')->where('uname', 'admin')->first();

        if ($admin) {
            // Delete admin permissions
            if (Schema::hasTable('admin_permissions')) {
                DB::table('admin_permissions')->where('user_id', $admin->id)->delete();
            }

            // Delete admin user
            DB::table('users')->where('id', $admin->id)->delete();
        }

        // Optionally remove added columns (commented out to preserve data)
        // Schema::table('users', function (Blueprint $table) {
        //     $columnsToRemove = ['login_type', 'status', 'account_locked', 'profile_edit_allowed', 'api_type', 'ip_restriction_enabled', 'security_settings_updated_at', 'last_login'];
        //     foreach ($columnsToRemove as $column) {
        //         if (Schema::hasColumn('users', $column)) {
        //             $table->dropColumn($column);
        //         }
        //     }
        // });
    }
};
