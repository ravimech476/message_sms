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
        Schema::table('cron_job_logs', function (Blueprint $table) {
            $table->string('log_file', 500)->nullable()->after('error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cron_job_logs', function (Blueprint $table) {
            $table->dropColumn('log_file');
        });
    }
};
