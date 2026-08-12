<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add file upload support to contracts table
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('contracts', 'file_path')) {
                $table->string('file_path')->nullable()->after('content');
            }
            if (!Schema::hasColumn('contracts', 'file_name')) {
                $table->string('file_name')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('contracts', 'file_size')) {
                $table->bigInteger('file_size')->nullable()->after('file_name');
            }
            if (!Schema::hasColumn('contracts', 'file_type')) {
                $table->string('file_type', 50)->nullable()->after('file_size');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $columns = ['file_path', 'file_name', 'file_size', 'file_type'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
