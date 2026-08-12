<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds the "DLR Status Update" settings-tab permission.
     *
     * Auto-permission (mirrors can_view_daemon_report): the column defaults to 1 so every
     * newly-created admin_permissions row gets it, and all existing admins are backfilled —
     * so the tab appears for present and future admins with no re-configuration.
     * (Super admins already bypass permission checks via role === 'super_admin'.)
     */
    public function up(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_permissions', 'can_manage_dlr_update')) {
                $table->boolean('can_manage_dlr_update')->default(true)->after('can_run_queries');
            }
        });

        // Default ON for future rows.
        DB::statement('ALTER TABLE admin_permissions MODIFY can_manage_dlr_update TINYINT(1) NOT NULL DEFAULT 1');

        // Backfill everyone who already exists.
        DB::table('admin_permissions')->update(['can_manage_dlr_update' => 1]);
    }

    public function down(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('admin_permissions', 'can_manage_dlr_update')) {
                $table->dropColumn('can_manage_dlr_update');
            }
        });
    }
};
