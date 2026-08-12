<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table already exists
        if (!Schema::hasTable('contract_signatures')) {
            Schema::create('contract_signatures', function (Blueprint $table) {
                $table->id();
                
                // Foreign keys - using unsignedBigInteger to match users.id and contracts.id
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('contract_id');
                
                // Signature details
                $table->string('signee_name');
                $table->string('signee_email');
                $table->string('signee_position');
                $table->longText('signature_data')->nullable(); // For digital signature image/data
                
                // Tracking information
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('signed_at');
                
                $table->timestamps();
                
                // Add foreign key constraints
                // $table->foreign('user_id')
                //     ->references('id')
                //     ->on('users')
                //     ->onDelete('cascade');
                    
                // $table->foreign('contract_id')
                //     ->references('id')
                //     ->on('contracts')
                //     ->onDelete('cascade');
                
                // Unique constraint - one signature per user per contract
                $table->unique(['user_id', 'contract_id'], 'user_contract_unique');
                
                // Add indexes for better performance
                $table->index('contract_id');
                $table->index('signed_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_signatures');
    }
};
