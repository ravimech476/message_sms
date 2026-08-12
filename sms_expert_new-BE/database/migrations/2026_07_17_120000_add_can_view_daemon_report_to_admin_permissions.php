<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds the "SMSG Daemon Report" (Livebeat) permission.
     *
     * Auto-permission: existing admin_permissions rows are granted this permission
     * so that current admins keep seeing all report menus without re-configuration.
     * (Super admins already bypass permission checks via User::isSuperAdmin().)
     */
    public function up(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_permissions', 'can_view_daemon_report')) {
                $table->boolean('can_view_daemon_report')->default(false)->after('can_view_monthly_sales_report');
            }
        });

        // Auto-grant to all existing admins so the new menu appears for them immediately.
        DB::table('admin_permissions')->update(['can_view_daemon_report' => 1]);
    }

    public function down(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('admin_permissions', 'can_view_daemon_report')) {
                $table->dropColumn('can_view_daemon_report');
            }
        });
    }
};
