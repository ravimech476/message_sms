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
     * Make content column nullable for file-only contracts
     */
    public function up(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'content')) {
            // For MySQL, we need to use raw SQL to modify the column
            DB::statement('ALTER TABLE contracts MODIFY COLUMN content LONGTEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't reverse this as it might cause data issues
    }
};
