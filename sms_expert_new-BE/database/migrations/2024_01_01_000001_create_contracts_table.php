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
        // Check if table already exists
        if (!Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->longText('content')->nullable();
                $table->enum('type', ['main', 'addendum'])->default('main');
                $table->boolean('is_active')->default(true);
                $table->boolean('requires_signature')->default(false);
                $table->integer('version')->default(1);
                $table->timestamps();
                $table->softDeletes();
                
                // Add indexes for better performance
                $table->index('type');
                $table->index('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
