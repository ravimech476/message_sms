<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMPP Sequence IDs Table - OLD SYSTEM Compatible
 *
 * Manages unique message IDs within assigned ranges per daemon.
 * Based on OLD SYSTEM seq_id_range pattern from thisserver_details.inc
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('smpp_sequence_ids', function (Blueprint $table) {
            $table->id();
            $table->string('daemon_id', 10)->unique()->comment('Daemon identifier (a, b, c, a0, a3, etc.)');
            $table->unsignedBigInteger('current_id')->default(1)->comment('Current sequence ID');
            $table->unsignedBigInteger('min_id')->default(1)->comment('Minimum ID in range');
            $table->unsignedBigInteger('max_id')->default(2000000)->comment('Maximum ID in range');
            $table->timestamps();

            $table->index('daemon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smpp_sequence_ids');
    }
};
