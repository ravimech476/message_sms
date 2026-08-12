<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the Monthly Sales Report tab permission column so admins can be granted
     * access to the new Reports > Monthly Sales tab.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_permissions')
            && !Schema::hasColumn('admin_permissions', 'can_view_monthly_sales_report')) {
            Schema::table('admin_permissions', function (Blueprint $table) {
                $table->boolean('can_view_monthly_sales_report')
                    ->default(0)
                    ->after('can_view_money_transfer_report');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_permissions')
            && Schema::hasColumn('admin_permissions', 'can_view_monthly_sales_report')) {
            Schema::table('admin_permissions', function (Blueprint $table) {
                $table->dropColumn('can_view_monthly_sales_report');
            });
        }
    }
};
