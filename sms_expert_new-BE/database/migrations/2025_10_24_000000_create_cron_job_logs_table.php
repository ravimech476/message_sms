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
        Schema::create('cron_job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->string('status')->default('pending'); // pending, running, success, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('duration')->nullable()->comment('Duration in seconds');
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            
            $table->index('command');
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_job_logs');
    }
};
