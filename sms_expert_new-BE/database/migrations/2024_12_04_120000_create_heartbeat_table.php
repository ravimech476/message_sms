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
        // Only create if table doesn't exist (it may exist from legacy system)
        if (!Schema::hasTable('heartbeat')) {
            Schema::create('heartbeat', function (Blueprint $table) {
                $table->id();
                $table->string('bigid', 32)->unique()->comment('Unique reference for the heartbeat');
                $table->string('smsgref', 32)->nullable()->comment('SMS gateway reference');
                $table->string('senderid', 20)->nullable()->comment('Sender ID used');
                $table->string('mobnum', 20)->comment('Mobile number sent to');
                $table->integer('parts')->default(1)->comment('Number of message parts');
                $table->text('text')->nullable()->comment('Message content');
                $table->string('smssenttime', 14)->nullable()->comment('Time SMS was sent (YmdHis format)');
                $table->string('smsreceivedtime', 14)->nullable()->comment('Time SMS was received (YmdHis format)');
                $table->string('dlrreceivedtime', 14)->nullable()->comment('Time DLR was received (YmdHis format)');
                $table->string('route', 5)->nullable()->comment('SMS route used');
                $table->string('network', 20)->nullable()->comment('Network name (Voda, O2, EE, Three, Orange)');
                $table->string('smsstatus', 50)->nullable()->default(null)->comment('SMS send status');
                $table->string('dlrstatus', 50)->nullable()->default(null)->comment('Delivery receipt status');
                $table->timestamps();

                $table->index('smssenttime');
                $table->index('network');
                $table->index(['smsstatus', 'dlrstatus']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heartbeat');
    }
};
