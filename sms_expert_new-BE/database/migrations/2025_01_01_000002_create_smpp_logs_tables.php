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
        // SMPP Logs table for detailed logging
        Schema::create('smpp_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['REQUEST', 'RESPONSE', 'RECEIVED']);
            $table->string('command', 50);
            $table->string('command_id', 20);
            $table->integer('command_status')->default(0);
            $table->string('status_message')->nullable();
            $table->integer('sequence_number');
            $table->longText('data')->nullable();
            $table->timestamps();
            
            $table->index('created_at');
            $table->index('command');
            $table->index('direction');
        });

        // Incoming SMS table for MO messages
        Schema::create('incoming_sms', function (Blueprint $table) {
            $table->id();
            $table->string('from_number', 20);
            $table->string('to_number', 20);
            $table->text('message');
            $table->string('country_code', 10)->nullable();
            $table->timestamp('received_at');
            $table->longText('metadata')->nullable();
            $table->timestamps();
            
            $table->index('from_number');
            $table->index('to_number');
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_sms');
        Schema::dropIfExists('smpp_logs');
    }
};
