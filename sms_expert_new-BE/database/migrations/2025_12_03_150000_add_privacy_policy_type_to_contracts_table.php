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
     * This migration adds 'privacy_policy' as a valid type for contracts.
     */
    public function up(): void
    {
        // Check if contracts table exists
        if (Schema::hasTable('contracts')) {
            // For MySQL: Modify the ENUM column to include 'privacy_policy'
            // We need to use raw SQL because Laravel doesn't support modifying ENUM values directly
            DB::statement("ALTER TABLE contracts MODIFY COLUMN type ENUM('main', 'addendum', 'privacy_policy') DEFAULT 'main'");
            
            // Log the migration
            \Log::info('Migration: Added privacy_policy type to contracts table');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            // First, update any privacy_policy records to 'main' to avoid data loss
            DB::table('contracts')
                ->where('type', 'privacy_policy')
                ->update(['type' => 'main']);
            
            // Then revert the ENUM back to original values
            DB::statement("ALTER TABLE contracts MODIFY COLUMN type ENUM('main', 'addendum') DEFAULT 'main'");
            
            \Log::info('Migration Rollback: Removed privacy_policy type from contracts table');
        }
    }
};
