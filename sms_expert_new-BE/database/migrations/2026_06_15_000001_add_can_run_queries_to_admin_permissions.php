<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the `can_run_queries` settings-tab permission — controls access to the
     * Settings > Query (read-only SQL console) tab.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_permissions') && !Schema::hasColumn('admin_permissions', 'can_run_queries')) {
            Schema::table('admin_permissions', function (Blueprint $table) {
                $table->boolean('can_run_queries')->default(false)->after('can_manage_server_settings');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_permissions') && Schema::hasColumn('admin_permissions', 'can_run_queries')) {
            Schema::table('admin_permissions', function (Blueprint $table) {
                $table->dropColumn('can_run_queries');
            });
        }
    }
};
