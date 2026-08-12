<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminRole extends Model
{
    use HasFactory;

    protected $table = 'admin_roles';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'is_system',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the users with this role.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'admin_role_id');
    }

    /**
     * Get the creator of this role.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->slug === 'super_admin') {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Get default system roles.
     */
    public static function getDefaultRoles(): array
    {
        $allPermissions = AdminPermission::getPermissionFields();

        return [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Full access to all features and settings',
                'permissions' => $allPermissions,
                'is_system' => true,
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Access to most features except critical settings',
                'permissions' => array_diff($allPermissions, ['can_view_env_variables', 'can_manage_admin_users']),
                'is_system' => true,
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Access to customer management and reports',
                'permissions' => [
                    'can_view_dashboard',
                    'can_view_customers',
                    'can_view_customer_emails',
                    'can_view_virtual_numbers',
                    'can_view_reports',
                    'can_view_customer_profile',
                    'can_edit_customer_profile',
                    'can_view_customer_keywords',
                    'can_view_customer_virtual_numbers',
                    'can_view_customer_notes',
                    'can_edit_customer_notes',
                    'can_view_customer_wallet',
                    'can_view_customer_logs',
                    'can_view_customer_reports',
                    'can_view_customer_flag',
                    'can_create_customers',
                    'can_export_data',
                    'can_view_postpay_report',
                    'can_view_daily_sms_report',
                    'can_view_money_transfer_report',
                    'can_view_monthly_sales_report',
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Limited access to view customers and basic features',
                'permissions' => [
                    'can_view_dashboard',
                    'can_view_customers',
                    'can_view_customer_profile',
                    'can_view_customer_keywords',
                    'can_view_customer_virtual_numbers',
                    'can_view_customer_notes',
                    'can_view_customer_logs',
                    'can_view_customer_flag',
                ],
                'is_system' => true,
            ],
        ];
    }

    /**
     * Scope active roles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
