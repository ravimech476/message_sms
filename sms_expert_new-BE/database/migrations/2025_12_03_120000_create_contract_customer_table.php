<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This creates the pivot table for the many-to-many relationship
     * between contracts and customers (users).
     * 
     * Note: Foreign key constraints are not used due to legacy database structure.
     * Referential integrity is enforced at the application level.
     */
    public function up(): void
    {
        if (!Schema::hasTable('contract_customer')) {
            // Disable foreign key checks temporarily
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            
            Schema::create('contract_customer', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_id');
                $table->unsignedBigInteger('customer_id');
                $table->timestamps();

                // Unique constraint to prevent duplicate entries
                $table->unique(['contract_id', 'customer_id']);
                
                // Indexes for better query performance
                $table->index('contract_id');
                $table->index('customer_id');
            });
            
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_customer');
    }
};
