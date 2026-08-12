<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\AdminPermission;

class FixOldAdminUsers extends Command
{
    protected $signature = 'admin:fix-old-users';
    protected $description = 'Fix old admin users by setting them as super_admin with full permissions';

    public function handle()
    {
        $this->info('Finding old admin users without role...');
        
        // Find admin users without role set
        $oldAdmins = User::where('login_type', 'admin')
            ->whereNull('role')
            ->get();

        if ($oldAdmins->isEmpty()) {
            $this->info('No old admin users found that need fixing.');
            return;
        }

        $this->info("Found {$oldAdmins->count()} old admin user(s) to fix.");

        foreach ($oldAdmins as $admin) {
            $this->line("Processing: {$admin->contactname} ({$admin->uname})");
            
            // Set as super_admin
            $admin->role = 'super_admin';
            $admin->save();
            
            // Check if permissions record exists
            $permission = AdminPermission::where('user_id', $admin->id)->first();
            
            if (!$permission) {
                // Create permissions with all access for super_admin
                $permission = new AdminPermission(['user_id' => $admin->id]);
                foreach (AdminPermission::getPermissionFields() as $field) {
                    $permission->$field = true;
                }
                $permission->save();
                $this->info("  - Created permissions record with full access");
            } else {
                $this->info("  - Permissions record already exists");
            }
            
            $this->info("  - Set as Super Admin");
        }

        $this->info('Done! Old admin users have been fixed.');
    }
}
