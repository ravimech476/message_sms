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
            // Settings tab permissions - only add if they don't exist
            if (!Schema::hasColumn('admin_permissions', 'can_view_system_logs')) {
                $table->boolean('can_view_system_logs')->default(false)->after('can_export_data');
            }
            if (!Schema::hasColumn('admin_permissions', 'can_view_user_activity')) {
                $table->boolean('can_view_user_activity')->default(false)->after('can_view_system_logs');
            }
            if (!Schema::hasColumn('admin_permissions', 'can_view_env_variables')) {
                $table->boolean('can_view_env_variables')->default(false)->after('can_view_user_activity');
            }
            if (!Schema::hasColumn('admin_permissions', 'can_manage_cache')) {
                $table->boolean('can_manage_cache')->default(false)->after('can_view_env_variables');
            }
            if (!Schema::hasColumn('admin_permissions', 'can_view_process_monitor')) {
                $table->boolean('can_view_process_monitor')->default(false)->after('can_manage_cache');
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
                'can_view_system_logs',
                'can_view_user_activity',
                'can_view_env_variables',
                'can_manage_cache',
                'can_view_process_monitor',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('admin_permissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
