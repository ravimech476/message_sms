<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{

    use HasFactory,
        Notifiable,
        HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table = 'users';
    public $timestamps = false;
    protected $fillable = [
        'bigid',
        'first_ip',
        'busname',
        'uname',
        'pword',
        'contactname',
        'address1',
        'address2',
        'town',
        'city',
        'county',
        'pcode',
        'country',
        'phone',
        'contactemail',
        'website',        // OLD SYSTEM "Web (alt)"
        'contactemail2',  // OLD SYSTEM "Emails (extra)"
        'datefrozen',
        'mobilenumber',
        'smsg_wallet',
        'user_type',
        'masteruname',
        'nfckey',
        'customer_type',
        'introducerref',
        'subusrkey',
        'numpooledvirtsUK',
        'numpooledvirtsUSA',
        'walletminlevel',
        'meetlocation',
        'itaggsecretkey',
        'badhlrlastwarned',
        'shopify_cust_id',
        'anondesc',
        'custtype',
        'bulksms_tally_last_reset',
        'forgot_password1_link',
        'forgot_password1_submit',
        'login_type',
        'bulk_throughput',
        'platinumaccess',
        'bit_disabled',
        'platkeywordwallet',
        'walletloan',
        'newrate',
        'whatsapp_enabled',
        'role',
        'admin_role_id',
        'ip_address_restriction',
        'last_login_at',
        'last_login_ip',
        'created_by',
        'migration_flag',
        // OLD SYSTEM: Wallet and pricing fields (added for customer creation)
        'smsg_server1_sent',
        'smsg_server2_sent',
        'premium_throughput',
        'daemonpriority',
        'dashboardaccess',
        'chargetype1',
        'routetag',
        '1s_preferredroute',
        'clientcommstatus',
        'affiliateinvitecode',
        'datejoined',
        'isnfcuser',
        'lab2id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function reminders()
    {
        return $this->hasMany(UserReminder::class, 'usersbigidref', 'bigid');
    }

    public function options()
    {
        return $this->hasMany(UserOption::class, 'userref', 'bigid');
    }

    public function option()
    {
        return $this->hasOne(UserOption::class, 'userbigid', 'bigid');
    }

    /**
     * Get the margin for the user.
     */
    public function margin()
    {
        return $this->hasOne(UserMargin::class, 'user_id', 'id');
    }

    /**
     * Calculate user rate for a given base cost based on margin
     *
     * @param float $baseCostGBP
     * @return float
     */
    public function calculateRate($baseCostGBP)
    {
        $margin = $this->margin;
        
        if (!$margin || !$margin->is_active) {
            return $baseCostGBP;
        }
        
        return $margin->calculateUserRate($baseCostGBP);
    }

    /**
     * Get margin percentage for the user
     *
     * @return float
     */
    public function getMarginPercentage()
    {
        $margin = $this->margin;
        return $margin ? $margin->margin_percentage : 0;
    }

    /**
     * Check if user has active margin
     *
     * @return bool
     */
    public function hasActiveMargin()
    {
        $margin = $this->margin;
        return $margin && $margin->is_active;
    }

    // ==================== ADMIN USER METHODS ====================

    /**
     * Get the admin permissions for this user.
     */
    public function adminPermissions()
    {
        return $this->hasOne(AdminPermission::class, 'user_id', 'id');
    }

    /**
     * Get the admin role for this user.
     */
    public function adminRole()
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }

    /**
     * Get the activity logs for this admin user.
     */
    public function adminActivityLogs()
    {
        return $this->hasMany(AdminActivityLog::class, 'user_id', 'id');
    }

    /**
     * Get the admin who created this user.
     */
    public function createdByAdmin()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->login_type === 'admin';
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->login_type === 'admin' && $this->role === 'super_admin';
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        // Super admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Non-admin users don't have admin permissions
        if (!$this->isAdmin()) {
            return false;
        }

        $permissions = $this->adminPermissions;
        
        if (!$permissions) {
            return false;
        }

        return (bool) $permissions->$permission;
    }

    /**
     * Check if the user can access a menu.
     */
    public function canAccessMenu(string $menu): bool
    {
        $menuPermissions = [
            'dashboard' => 'can_view_dashboard',
            'customers' => 'can_view_customers',
            'customer_emails' => 'can_view_customer_emails',
            'virtual_numbers' => 'can_view_virtual_numbers',
            'reports' => 'can_view_reports',
            'settings' => 'can_view_settings',
            'admin_users' => 'can_manage_admin_users',
        ];

        if (!isset($menuPermissions[$menu])) {
            return false;
        }

        return $this->hasPermission($menuPermissions[$menu]);
    }

    /**
     * Check if the user can access a customer tab.
     */
    public function canAccessCustomerTab(string $tab): bool
    {
        $tabPermissions = [
            'profile' => 'can_view_customer_profile',
            'keywords' => 'can_view_customer_keywords',
            'virtual_numbers' => 'can_view_customer_virtual_numbers',
            'notes' => 'can_view_customer_notes',
            'wallet' => 'can_view_customer_wallet',
            'logs' => 'can_view_customer_logs',
            'reports' => 'can_view_customer_reports',
            'Migration' => 'can_view_customer_flag',
        ];

        if (!isset($tabPermissions[$tab])) {
            return false;
        }

        return $this->hasPermission($tabPermissions[$tab]);
    }

    /**
     * Check if the user can edit a customer tab.
     */
    public function canEditCustomerTab(string $tab): bool
    {
        $tabPermissions = [
            'profile' => 'can_edit_customer_profile',
            'keywords' => 'can_edit_customer_keywords',
            'virtual_numbers' => 'can_edit_customer_virtual_numbers',
            'notes' => 'can_edit_customer_notes',
            'wallet' => 'can_edit_customer_wallet',
        ];

        if (!isset($tabPermissions[$tab])) {
            return false;
        }

        return $this->hasPermission($tabPermissions[$tab]);
    }

    /**
     * Get role display name.
     */
    public function getRoleDisplayName(): string
    {
        $roles = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'staff' => 'Staff',
        ];

        return $roles[$this->role] ?? ($this->role ?? 'N/A');
    }

    /**
     * Get all available permissions.
     */
    public static function getAvailablePermissions(): array
    {
        return [
            'menu_permissions' => [
                'can_view_dashboard' => 'View Dashboard',
                'can_view_customers' => 'View Customers',
                'can_view_customer_emails' => 'View Customer Emails',
                'can_view_virtual_numbers' => 'View Virtual Numbers',
                'can_view_reports' => 'View Reports',
                'can_view_settings' => 'View Settings',
                'can_manage_admin_users' => 'Manage Admin Users',
                'can_manage_contracts' => 'Manage Contracts',
                'can_manage_cost' => 'Manage Cost',
                'can_manage_global_pricing' => 'Global Pricing (Menu Access)',
            ],
            'customer_tab_permissions' => [
                'can_view_customer_profile' => 'View Customer Profile',
                'can_edit_customer_profile' => 'Edit Customer Profile',
                'can_view_customer_rate' => 'View Customer Rate',
                'can_view_customer_keywords' => 'View Customer Keywords',
                'can_edit_customer_keywords' => 'Edit Customer Keywords',
                'can_view_customer_virtual_numbers' => 'View Customer Virtual Numbers',
                'can_edit_customer_virtual_numbers' => 'Edit Customer Virtual Numbers',
                'can_view_customer_notes' => 'View Customer Notes',
                'can_edit_customer_notes' => 'Edit Customer Notes',
                'can_view_customer_wallet' => 'View Customer Wallet',
                'can_edit_customer_wallet' => 'Edit Customer Wallet',
                'can_view_customer_logs' => 'View Customer Logs',
                'can_view_customer_reports' => 'View Customer Reports',
                'can_view_customer_flag' => 'View Customer Flag',
            ],
            'action_permissions' => [
                'can_create_customers' => 'Create Customers',
                'can_delete_customers' => 'Delete Customers',
                'can_export_data' => 'Export Data',
            ],
            'settings_tab_permissions' => [
                'can_view_system_logs' => 'System Logs',
                'can_manage_cache' => 'Cache Management',
                'can_view_process_monitor' => 'Process Monitor',
                'can_view_queues' => 'Queues',
                'can_manage_customer_settings' => 'Customer Settings (Tab Access)',
                'can_manage_server_settings' => 'Server Settings',
                'can_run_queries' => 'Query Console (Tab Access)',
                'can_manage_dlr_update' => 'DLR Status Update (Tab Access)',
            ],
            'customer_settings_sub_permissions' => [
                'can_manage_general_customer_settings' => 'General Customer Settings',
                'can_manage_global_maintenance' => 'Global Maintenance Mode',
                'can_manage_customer_maintenance' => 'Customer-Specific Maintenance',
            ],
            'notification_permissions' => [
                'can_manage_notifications' => 'Manage Notifications',
            ],
            'reports_tab_permissions' => [
                'can_view_postpay_report' => 'Post-Pay Report',
                'can_view_daily_sms_report' => 'Daily SMS Report',
                'can_view_money_transfer_report' => 'Money Transferred Report',
                'can_view_monthly_sales_report' => 'Sales Report',
            ],
            'other_links_permissions' => [
                'can_view_daemon_report' => 'Livebeat',
            ],
        ];
    }

    /**
     * Scope for admin users only.
     */
    public function scopeAdminUsers($query)
    {
        return $query->where('login_type', 'admin');
    }

    /**
     * Scope for customer users only.
     */
    public function scopeCustomerUsers($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('login_type')
                ->orWhere('login_type', 'customer');
        });
    }

    // ==================== MIGRATION FLAG SCOPES ====================

    /**
     * Scope for users migrated to new system (migration_flag = 'new').
     * Use this in cron jobs to only process users on the new system.
     */
    public function scopeMigratedToNew($query)
    {
        return $query->where('migration_flag', 'new');
    }

    /**
     * Scope for users still on old system (migration_flag = 'old' or null).
     */
    public function scopeMigratedToOld($query)
    {
        return $query->where(function ($q) {
            $q->where('migration_flag', 'old')
                ->orWhereNull('migration_flag');
        });
    }

    /**
     * Alias for migratedToNew - clearer name for cron jobs.
     * Use this scope in all cron commands to filter users.
     */
    public function scopeForNewSystem($query)
    {
        return $query->migratedToNew();
    }

    /**
     * Check if user is migrated to new system.
     */
    public function isMigratedToNew(): bool
    {
        return $this->migration_flag === 'new';
    }

    /**
     * Check if user is on old system.
     */
    public function isOnOldSystem(): bool
    {
        return $this->migration_flag === 'old' || $this->migration_flag === null;
    }
}
