<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmppMessageMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('smpp_message_mapping', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 255)->index()->comment('SMPP message ID from carrier');
            $table->string('bigid', 32)->index()->comment('Reference ID from smsg_log');
            $table->string('queue_id', 50)->nullable()->index()->comment('Queue ID for tracking');
            $table->string('mobile_number', 20)->nullable()->comment('Recipient mobile number');
            $table->timestamp('created_at')->useCurrent();
            
            // Add composite index for faster lookups
            $table->index(['message_id', 'bigid']);
            
            // Add unique constraint to prevent duplicates
            $table->unique(['message_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('smpp_message_mapping');
    }
}
