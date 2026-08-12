<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make the "Livebeat" (SMSG Daemon Report) permission automatic for ALL admins:
     *  - change the column default to 1 so any newly-created admin_permissions row
     *    that doesn't explicitly set it still gets the permission,
     *  - and (re)grant it to every existing admin.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('admin_permissions', 'can_view_daemon_report')) {
            Schema::table('admin_permissions', function ($table) {
                $table->boolean('can_view_daemon_report')->default(true);
            });
        }

        // Default ON for future rows.
        DB::statement('ALTER TABLE admin_permissions MODIFY can_view_daemon_report TINYINT(1) NOT NULL DEFAULT 1');

        // Backfill everyone who already exists.
        DB::table('admin_permissions')->update(['can_view_daemon_report' => 1]);
    }

    public function down(): void
    {
        // Revert the default back to 0 (leave existing grants in place).
        DB::statement('ALTER TABLE admin_permissions MODIFY can_view_daemon_report TINYINT(1) NOT NULL DEFAULT 0');
    }
};
