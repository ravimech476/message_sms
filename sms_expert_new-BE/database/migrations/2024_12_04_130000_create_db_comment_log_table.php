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
        if (!Schema::hasTable('db_comment_log')) {
            Schema::create('db_comment_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('record_id')->comment('ID of the record being logged');
                $table->string('table_name', 100)->comment('Name of the table');
                $table->text('comment')->comment('Log comment/message');
                $table->timestamps();

                $table->index(['table_name', 'record_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_comment_log');
    }
};
