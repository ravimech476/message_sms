<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_flag_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_id');

            $table->string('bigid')->nullable();

            $table->string('old_flag')->nullable();
            $table->string('new_flag');

            $table->unsignedBigInteger('changed_by')->nullable();

            $table->ipAddress('changed_ip')->nullable();

            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();

            $table->index('customer_id');
            $table->index('bigid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_flag_logs');
    }
};
