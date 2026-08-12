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
        Schema::create('campaign_file_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration_batch_id', 50)->index();
            $table->string('user_bigid', 64)->index();
            $table->string('direction')->comment('old_to_new or new_to_old');
            $table->string('filename');
            $table->string('source_path')->nullable();
            $table->string('destination_path')->nullable();
            $table->string('status')->default('pending')->comment('pending, processing, completed, skipped, failed');
            $table->text('status_message')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['migration_batch_id', 'status']);
            $table->index(['user_bigid', 'direction']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_file_migrations');
    }
};
