<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Mail\AdminUserCreatedMail;
use App\Services\Queue\EmailQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminUsersController extends Controller
{
    protected $emailQueueService;

    public function __construct()
    {
        // Initialize EmailQueueService (with error handling)
        try {
            $this->emailQueueService = new EmailQueueService();
        } catch (\Exception $e) {
            Log::warning('EmailQueueService initialization failed: ' . $e->getMessage());
            $this->emailQueueService = null;
        }
    }

    /**
     * Display a listing of admin users.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        $role = $request->get('role', '');

        $query = User::where('login_type', 'admin')
            ->with(['adminPermissions', 'adminRole'])
            ->orderBy('id', 'desc');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('contactname', 'like', "%{$search}%")
                    ->orWhere('contactemail', 'like', "%{$search}%")
                    ->orWhere('uname', 'like', "%{$search}%")
                    ->orWhere('busname', 'like', "%{$search}%");
            });
        }

        if (!empty($role)) {
            $query->where(function ($q) use ($role) {
                $q->where('role', $role)
                    ->orWhereHas('adminRole', function ($q2) use ($role) {
                        $q2->where('slug', $role);
                    });
            });
        }

        $adminUsers = $query->paginate($perPage);
        $roles = AdminRole::active()->orderBy('name')->get();

        return view('admin.admin-users.index', compact('adminUsers', 'search', 'role', 'perPage', 'roles'));
    }

    /**
     * Show the form for creating a new admin user.
     */
    public function create()
    {
        $permissions = User::getAvailablePermissions();
        $roles = AdminRole::active()->orderBy('name')->get();
        
        return view('admin.admin-users.create', compact('permissions', 'roles'));
    }

    /**
     * Generate unique bigid in MD5 hash format.
     */
    private function generateUniqueBigid(): string
    {
        do {
            // Generate MD5 hash from unique string (timestamp + random)
            $bigid = md5(uniqid(mt_rand(), true));

            // Check if this bigid already exists
            $exists = DB::table('users')->where('bigid', $bigid)->exists();
        } while ($exists);

        return $bigid;
    }

    /**
     * Store a newly created admin user.
     */
    public function store(Request $request)
    {
        // Scope uniqueness to admin users only — see update() for why.
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'email',
                Rule::unique('users', 'contactemail')
                    ->where(fn ($q) => $q->where('login_type', 'admin')),
            ],
            'username' => [
                'required', 'string', 'max:255',
                Rule::unique('users', 'uname')
                    ->where(fn ($q) => $q->where('login_type', 'admin')),
            ],
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|exists:admin_roles,id',
        ]);

        // Get current admin user from session
        $currentAdmin = Session::get('admin_user');

        // Get the selected role
        $role = AdminRole::findOrFail($request->role_id);

        // Generate unique bigid in MD5 hash format
        $newBigid = $this->generateUniqueBigid();

        // Store plain password for email before hashing
        $plainPassword = $request->password;

        $adminUser = User::create([
            'bigid' => $newBigid,
            'busname' => $request->name,
            'contactname' => $request->name,
            'contactemail' => $request->email,
            'uname' => $request->username,
            'pword' => md5($request->password), // Using md5 to match existing system
            'phone' => $request->phone ?? '',
            'login_type' => 'admin',
            'role' => $role->slug,
            'admin_role_id' => $role->id,
            'bit_disabled' => $request->has('is_active') ? 0 : 1,
            'ip_address_restriction' => 0,
            'created_by' => $currentAdmin['id'] ?? null,
        ]);

        // Create permissions based on role or custom selection
        $permissionData = ['user_id' => $adminUser->id];

        // If role is super_admin or using role permissions
        if ($role->slug === 'super_admin') {
            // Grant all permissions
            foreach (AdminPermission::getPermissionFields() as $field) {
                $permissionData[$field] = true;
            }
        } elseif ($request->has('use_role_permissions') && $request->use_role_permissions) {
            // Use role's predefined permissions
            $rolePermissions = $role->permissions ?? [];
            foreach (AdminPermission::getPermissionFields() as $field) {
                $permissionData[$field] = in_array($field, $rolePermissions);
            }
        } else {
            // Use custom permissions from form
            foreach (AdminPermission::getPermissionFields() as $field) {
                $permissionData[$field] = $request->has($field);
            }
        }

        AdminPermission::create($permissionData);

        // Send email with login details via RabbitMQ
        $emailSent = false;
        if ($request->has('send_email') || $request->send_email !== false) {
            $emailSent = $this->queueAdminCreatedEmail($adminUser, $plainPassword, $role->name);
        }

        $message = 'Admin user created successfully.';
        if ($emailSent) {
            $message .= ' Login details have been queued for delivery to ' . $adminUser->contactemail;
        }

        return redirect()->route('admin.admin-users.index')->with('success', $message);
    }

    /**
     * Queue admin created email via RabbitMQ
     */
    private function queueAdminCreatedEmail($adminUser, $password, $roleName): bool
    {
        try {
            if (!$this->emailQueueService) {
                $this->emailQueueService = new EmailQueueService();
            }

            // Prepare data for the mailable
            $emailData = [
                'admin_user' => [
                    'id' => $adminUser->id,
                    'contactname' => $adminUser->contactname,
                    'contactemail' => $adminUser->contactemail,
                    'uname' => $adminUser->uname,
                    'phone' => $adminUser->phone,
                ],
                'password' => $password,
                'role_name' => $roleName,
                'login_url' => env('ADMIN_DOMAIN', config('app.url') . '/admin') . '/login',
            ];

            // Queue the email
            $result = $this->emailQueueService->queueEmail(
                AdminUserCreatedMail::class,
                $adminUser->contactemail,
                $emailData,
                [], // No CC recipients
                8   // High priority
            );

            if ($result) {
                Log::info('Admin user created email queued via RabbitMQ', [
                    'user_id' => $adminUser->id,
                    'email' => $adminUser->contactemail
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to queue admin user created email', [
                'user_id' => $adminUser->id,
                'email' => $adminUser->contactemail,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Show the form for editing an admin user.
     */
    public function edit($id)
    {
        $adminUser = User::where('login_type', 'admin')
            ->with(['adminPermissions', 'adminRole'])
            ->findOrFail($id);
        $permissions = User::getAvailablePermissions();
        $roles = AdminRole::active()->orderBy('name')->get();

        return view('admin.admin-users.edit', compact('adminUser', 'permissions', 'roles'));
    }

    /**
     * Update the specified admin user.
     */
    public function update(Request $request, $id)
    {
        $adminUser = User::where('login_type', 'admin')->findOrFail($id);

        // Admin users live in the same `users` table as customers (with
        // login_type='admin'). Without the where() clause below the unique
        // checks would compare against EVERY row in `users`, so any customer
        // (or a soft-deleted admin) sharing the same email/uname would
        // wrongly fire "already taken" — which is what blocked updates on
        // the Basic Info / Role & Access / Permissions tabs.
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'email',
                Rule::unique('users', 'contactemail')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('login_type', 'admin')),
            ],
            'username' => [
                'required', 'string', 'max:255',
                Rule::unique('users', 'uname')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('login_type', 'admin')),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|exists:admin_roles,id',
        ]);

        $role = AdminRole::findOrFail($request->role_id);

        $adminUser->busname = $request->name;
        $adminUser->contactname = $request->name;
        $adminUser->contactemail = $request->email;
        $adminUser->uname = $request->username;
        $adminUser->phone = $request->phone ?? '';
        $adminUser->role = $role->slug;
        $adminUser->admin_role_id = $role->id;
        $adminUser->bit_disabled = $request->has('is_active') ? 0 : 1;

        if ($request->filled('password')) {
            $adminUser->pword = md5($request->password);
        }

        $adminUser->save();

        // Update permissions
        $permission = $adminUser->adminPermissions ?? new AdminPermission(['user_id' => $adminUser->id]);
        $permission->user_id = $adminUser->id;

        // If super_admin, grant all permissions
        if ($role->slug === 'super_admin') {
            foreach (AdminPermission::getPermissionFields() as $field) {
                $permission->$field = true;
            }
        } elseif ($request->has('use_role_permissions') && $request->use_role_permissions) {
            // Use role's predefined permissions
            $rolePermissions = $role->permissions ?? [];
            foreach (AdminPermission::getPermissionFields() as $field) {
                $permission->$field = in_array($field, $rolePermissions);
            }
        } else {
            foreach (AdminPermission::getPermissionFields() as $field) {
                $permission->$field = $request->has($field);
            }
        }

        $permission->save();

        // Refresh session if updating the currently logged-in admin user
        $currentAdmin = Session::get('admin_user');
        if ($currentAdmin && $currentAdmin['id'] == $adminUser->id) {
            // Reload permissions from database
            $adminPermissions = $permission;
            $permissionsArray = [
                // Menu permissions
                'can_view_dashboard' => (bool) $adminPermissions->can_view_dashboard,
                'can_view_customers' => (bool) $adminPermissions->can_view_customers,
                'can_view_customer_emails' => (bool) $adminPermissions->can_view_customer_emails,
                'can_view_virtual_numbers' => (bool) $adminPermissions->can_view_virtual_numbers,
                'can_view_reports' => (bool) $adminPermissions->can_view_reports,
                'can_view_settings' => (bool) $adminPermissions->can_view_settings,
                'can_manage_admin_users' => (bool) $adminPermissions->can_manage_admin_users,
                'can_manage_contracts' => (bool) ($adminPermissions->can_manage_contracts ?? false),
                'can_manage_cost' => (bool) ($adminPermissions->can_manage_cost ?? false),
                'can_manage_global_pricing' => (bool) ($adminPermissions->can_manage_global_pricing ?? false),
                // Customer tab permissions
                'can_view_customer_profile' => (bool) $adminPermissions->can_view_customer_profile,
                'can_edit_customer_profile' => (bool) $adminPermissions->can_edit_customer_profile,
                'can_view_customer_keywords' => (bool) $adminPermissions->can_view_customer_keywords,
                'can_edit_customer_keywords' => (bool) $adminPermissions->can_edit_customer_keywords,
                'can_view_customer_virtual_numbers' => (bool) $adminPermissions->can_view_customer_virtual_numbers,
                'can_edit_customer_virtual_numbers' => (bool) $adminPermissions->can_edit_customer_virtual_numbers,
                'can_view_customer_notes' => (bool) $adminPermissions->can_view_customer_notes,
                'can_edit_customer_notes' => (bool) $adminPermissions->can_edit_customer_notes,
                'can_view_customer_wallet' => (bool) $adminPermissions->can_view_customer_wallet,
                'can_edit_customer_wallet' => (bool) $adminPermissions->can_edit_customer_wallet,
                'can_view_customer_logs' => (bool) $adminPermissions->can_view_customer_logs,
                'can_view_customer_reports' => (bool) $adminPermissions->can_view_customer_reports,
                'can_view_customer_flag' => (bool) $adminPermissions->can_view_customer_flag,
                // Action permissions
                'can_create_customers' => (bool) $adminPermissions->can_create_customers,
                'can_delete_customers' => (bool) $adminPermissions->can_delete_customers,
                'can_export_data' => (bool) $adminPermissions->can_export_data,
                // Settings tab permissions
                'can_view_system_status' => (bool) ($adminPermissions->can_view_system_status ?? false),
                'can_view_system_logs' => (bool) ($adminPermissions->can_view_system_logs ?? false),
                'can_view_user_activity' => (bool) ($adminPermissions->can_view_user_activity ?? false),
                'can_view_env_variables' => (bool) ($adminPermissions->can_view_env_variables ?? false),
                'can_manage_cache' => (bool) ($adminPermissions->can_manage_cache ?? false),
                'can_view_process_monitor' => (bool) ($adminPermissions->can_view_process_monitor ?? false),
                'can_view_queues' => (bool) ($adminPermissions->can_view_queues ?? false),
                'can_manage_customer_settings' => (bool) ($adminPermissions->can_manage_customer_settings ?? false),
                'can_manage_server_settings' => (bool) ($adminPermissions->can_manage_server_settings ?? false),
                'can_run_queries' => (bool) ($adminPermissions->can_run_queries ?? false),
                'can_manage_dlr_update' => (bool) ($adminPermissions->can_manage_dlr_update ?? false),
                // Customer settings sub-permissions
                'can_manage_general_customer_settings' => (bool) ($adminPermissions->can_manage_general_customer_settings ?? false),
                'can_manage_global_maintenance' => (bool) ($adminPermissions->can_manage_global_maintenance ?? false),
                'can_manage_customer_maintenance' => (bool) ($adminPermissions->can_manage_customer_maintenance ?? false),
                // Notification permission
                'can_manage_notifications' => (bool) ($adminPermissions->can_manage_notifications ?? false),
                // Reports tab permissions
                'can_view_postpay_report' => (bool) ($adminPermissions->can_view_postpay_report ?? false),
                'can_view_daily_sms_report' => (bool) ($adminPermissions->can_view_daily_sms_report ?? false),
                'can_view_money_transfer_report' => (bool) ($adminPermissions->can_view_money_transfer_report ?? false),
                'can_view_monthly_sales_report' => (bool) ($adminPermissions->can_view_monthly_sales_report ?? false),
                'can_view_daemon_report' => (bool) ($adminPermissions->can_view_daemon_report ?? false),
                'can_view_customer_rate' => (bool) ($adminPermissions->can_view_customer_rate ?? false),
            ];

            // Update session with new permissions
            Session::put('admin_user', [
                'id' => $adminUser->id,
                'bigid' => $adminUser->bigid,
                'username' => $adminUser->uname,
                'name' => $adminUser->contactname,
                'email' => $adminUser->contactemail,
                'role' => $adminUser->role,
                'login_type' => $adminUser->login_type,
                'permissions' => $permissionsArray,
            ]);
        }

        return redirect()->route('admin.admin-users.index')->with('success', 'Admin user updated successfully.');
    }

    /**
     * Remove the specified admin user.
     */
    public function destroy($id)
    {
        $adminUser = User::where('login_type', 'admin')->findOrFail($id);

        // Prevent deleting yourself
        $currentAdmin = Session::get('admin_user');
        if ($currentAdmin && $currentAdmin['id'] == $id) {
            return redirect()->route('admin.admin-users.index')->with('error', 'You cannot delete your own account.');
        }

        // Delete permissions first
        AdminPermission::where('user_id', $id)->delete();

        // Delete the user
        $adminUser->delete();

        return redirect()->route('admin.admin-users.index')->with('success', 'Admin user deleted successfully.');
    }

    /**
     * Toggle admin user status.
     */
    public function toggleStatus($id)
    {
        $adminUser = User::where('login_type', 'admin')->findOrFail($id);

        // Prevent deactivating yourself
        $currentAdmin = Session::get('admin_user');
        if ($currentAdmin && $currentAdmin['id'] == $id) {
            return response()->json(['success' => false, 'message' => 'You cannot deactivate your own account.'], 400);
        }

        $adminUser->bit_disabled = $adminUser->bit_disabled ? 0 : 1;
        $adminUser->save();

        return response()->json([
            'success' => true,
            'is_active' => !$adminUser->bit_disabled,
            'message' => 'Status updated successfully.'
        ]);
    }

    /**
     * Store a new role.
     */
    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
        ]);

        $currentAdmin = Session::get('admin_user');

        // Generate slug from name
        $slug = strtolower(str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s]/', '', $request->name)));

        // Ensure unique slug
        $baseSlug = $slug;
        $counter = 1;
        while (AdminRole::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '_' . $counter;
            $counter++;
        }

        $role = AdminRole::create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'permissions' => $request->permissions ?? [],
            'is_system' => false,
            'is_active' => true,
            'created_by' => $currentAdmin['id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'role' => $role,
            'message' => 'Role created successfully.'
        ]);
    }

    /**
     * Update a role.
     */
    public function updateRole(Request $request, $id)
    {
        $role = AdminRole::findOrFail($id);

        // Prevent editing system roles
        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be modified.'
            ], 400);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
            'permissions' => $request->permissions ?? [],
        ]);

        return response()->json([
            'success' => true,
            'role' => $role,
            'message' => 'Role updated successfully.'
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroyRole($id)
    {
        $role = AdminRole::findOrFail($id);

        // Prevent deleting system roles
        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be deleted.'
            ], 400);
        }

        // Check if role is in use
        $usersWithRole = User::where('admin_role_id', $id)->count();
        if ($usersWithRole > 0) {
            return response()->json([
                'success' => false,
                'message' => "This role is assigned to {$usersWithRole} user(s). Please reassign them before deleting."
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.'
        ]);
    }

    /**
     * Get role permissions for AJAX.
     */
    public function getRolePermissions($id)
    {
        $role = AdminRole::findOrFail($id);

        return response()->json([
            'success' => true,
            'permissions' => $role->permissions ?? [],
            'role' => $role
        ]);
    }

    /**
     * Resend login credentials email via RabbitMQ.
     */
    public function resendCredentials(Request $request, $id)
    {
        $adminUser = User::where('login_type', 'admin')->findOrFail($id);

        // Generate a new temporary password
        $newPassword = $this->generateRandomPassword();

        // Update password
        $adminUser->pword = md5($newPassword);
        $adminUser->save();

        // Get role name
        $roleName = $adminUser->adminRole ? $adminUser->adminRole->name : ucfirst(str_replace('_', ' ', $adminUser->role ?? 'Admin'));

        // Send email via RabbitMQ
        $emailSent = $this->queueAdminCreatedEmail($adminUser, $newPassword, $roleName);

        if ($emailSent) {
            return response()->json([
                'success' => true,
                'message' => 'New login credentials have been queued for delivery to ' . $adminUser->contactemail
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue email. Please check RabbitMQ connection.'
            ], 500);
        }
    }

    /**
     * Generate random password.
     */
    private function generateRandomPassword($length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}
