<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            // Reports tab permissions - only add if they don't exist
            if (!Schema::hasColumn('admin_permissions', 'can_view_postpay_report')) {
                $table->boolean('can_view_postpay_report')->default(false)->after('can_view_process_monitor');
            }
            if (!Schema::hasColumn('admin_permissions', 'can_view_daily_sms_report')) {
                $table->boolean('can_view_daily_sms_report')->default(false)->after('can_view_postpay_report');
            }
            if (!Schema::hasColumn('admin_permissions', 'can_view_money_transfer_report')) {
                $table->boolean('can_view_money_transfer_report')->default(false)->after('can_view_daily_sms_report');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            $columns = [
                'can_view_postpay_report',
                'can_view_daily_sms_report',
                'can_view_money_transfer_report',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('admin_permissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
