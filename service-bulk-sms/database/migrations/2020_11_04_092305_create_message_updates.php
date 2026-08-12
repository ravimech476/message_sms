<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessageUpdates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('message_updates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_id');
            $table->enum('delivery_type', ['sms', 'email']);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->string('status_note')->nullable();
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['message_type', 'status', 'status_note']);

            $table->binary('message_data')->after('thread_item_id');
            $table->unsignedBigInteger('thread_id')->nullable()->after('provider_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('message_updates');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['thread_id', 'message_data']);

            $table->enum('message_type', ['sms', 'email'])->default('sms')->after('provider_id');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending')->after('message_type');
            $table->string('status_note')->nullable()->after('status');
        });
    }
}
