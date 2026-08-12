<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddCustomerIdToContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if column doesn't exist before adding
        if (!Schema::hasColumn('contracts', 'customer_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                // Add customer_id column (nullable - null means "all customers")
                // References users.id (enforced at application level, not database level)
                $table->unsignedBigInteger('customer_id')->nullable()->after('id');
            });
        }
        
        // Check if index doesn't exist before adding
        $indexExists = DB::select("SHOW INDEX FROM contracts WHERE Key_name = 'contracts_customer_id_index'");
        
        if (empty($indexExists)) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->index('customer_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Drop index if exists
            $indexExists = DB::select("SHOW INDEX FROM contracts WHERE Key_name = 'contracts_customer_id_index'");
            if (!empty($indexExists)) {
                $table->dropIndex(['customer_id']);
            }
            
            // Drop column if exists
            if (Schema::hasColumn('contracts', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
        });
    }
}
