<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\AdminRole;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create admin_roles table
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->longText('permissions')->nullable();
            $table->boolean('is_system')->default(false)->comment('System roles cannot be deleted');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
        });

        // Add admin_role_id to users table if it doesn't exist
        if (!Schema::hasColumn('users', 'admin_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_role_id')->nullable()->after('role');
                $table->index('admin_role_id');
            });
        }

        // Seed default roles
        $defaultRoles = AdminRole::getDefaultRoles();
        foreach ($defaultRoles as $roleData) {
            AdminRole::create([
                'name' => $roleData['name'],
                'slug' => $roleData['slug'],
                'description' => $roleData['description'],
                'permissions' => $roleData['permissions'],
                'is_system' => $roleData['is_system'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove admin_role_id from users table
        if (Schema::hasColumn('users', 'admin_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['admin_role_id']);
                $table->dropColumn('admin_role_id');
            });
        }

        Schema::dropIfExists('admin_roles');
    }
};
