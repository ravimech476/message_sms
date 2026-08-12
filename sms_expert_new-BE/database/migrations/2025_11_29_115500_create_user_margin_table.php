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
        Schema::create('user_margin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('References users.id');
            $table->decimal('margin_percentage', 10, 2)->default(0)->comment('Global margin percentage for all countries');
            $table->boolean('is_active')->default(1)->comment('Whether the margin is currently active');
            $table->timestamps();

            // Add index for faster lookups
            $table->index('user_id');
            
            // Ensure one margin per user
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_margin');
    }
};
