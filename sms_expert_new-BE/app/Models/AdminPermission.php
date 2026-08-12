<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminPermission extends Model
{
    use HasFactory;

    protected $table = 'admin_permissions';

    protected $fillable = [
        'user_id',
        // Menu permissions
        'can_view_dashboard',
        'can_view_customers',
        'can_view_customer_emails',
        'can_view_virtual_numbers',
        'can_view_reports',
        'can_view_settings',
        'can_manage_admin_users',
        'can_manage_contracts',
        'can_manage_cost',
        // Customer tab permissions
        'can_view_customer_profile',
        'can_edit_customer_profile',
        'can_view_customer_keywords',
        'can_edit_customer_keywords',
        'can_view_customer_virtual_numbers',
        'can_edit_customer_virtual_numbers',
        'can_view_customer_notes',
        'can_edit_customer_notes',
        'can_view_customer_wallet',
        'can_edit_customer_wallet',
        'can_view_customer_logs',
        'can_view_customer_reports',
        'can_view_customer_flag',
        // Action permissions
        'can_create_customers',
        'can_delete_customers',
        'can_export_data',
        // Settings tab permissions
        'can_view_system_status',
        'can_view_system_logs',
        'can_view_user_activity',
        'can_view_env_variables',
        'can_manage_cache',
        'can_view_process_monitor',
        'can_view_queues',
        'can_manage_customer_settings',
        'can_manage_server_settings',
        'can_run_queries',
        'can_manage_dlr_update',
        'can_manage_global_pricing',
        // Customer settings sub-permissions
        'can_manage_general_customer_settings',
        'can_manage_global_maintenance',
        'can_manage_customer_maintenance',
        // Notifications permission
        'can_manage_notifications',
        // Reports tab permissions
        'can_view_postpay_report',
        'can_view_daily_sms_report',
        'can_view_money_transfer_report',
        'can_view_monthly_sales_report',
        'can_view_daemon_report',
        'can_view_customer_rate'
    ];

    protected $casts = [
        'can_view_dashboard' => 'boolean',
        'can_view_customers' => 'boolean',
        'can_view_customer_emails' => 'boolean',
        'can_view_virtual_numbers' => 'boolean',
        'can_view_reports' => 'boolean',
        'can_view_settings' => 'boolean',
        'can_manage_admin_users' => 'boolean',
        'can_manage_contracts' => 'boolean',
        'can_manage_cost' => 'boolean',
        'can_view_customer_profile' => 'boolean',
        'can_edit_customer_profile' => 'boolean',
        'can_view_customer_keywords' => 'boolean',
        'can_edit_customer_keywords' => 'boolean',
        'can_view_customer_virtual_numbers' => 'boolean',
        'can_edit_customer_virtual_numbers' => 'boolean',
        'can_view_customer_notes' => 'boolean',
        'can_edit_customer_notes' => 'boolean',
        'can_view_customer_wallet' => 'boolean',
        'can_edit_customer_wallet' => 'boolean',
        'can_view_customer_logs' => 'boolean',
        'can_view_customer_reports' => 'boolean',
        'can_view_customer_flag' => 'boolean',
        'can_create_customers' => 'boolean',
        'can_delete_customers' => 'boolean',
        'can_export_data' => 'boolean',
        // Settings tab permissions
        'can_view_system_status' => 'boolean',
        'can_view_system_logs' => 'boolean',
        'can_view_user_activity' => 'boolean',
        'can_view_env_variables' => 'boolean',
        'can_manage_cache' => 'boolean',
        'can_view_process_monitor' => 'boolean',
        'can_view_queues' => 'boolean',
        'can_manage_customer_settings' => 'boolean',
        'can_manage_server_settings' => 'boolean',
        'can_run_queries' => 'boolean',
        'can_manage_dlr_update' => 'boolean',
        'can_manage_global_pricing' => 'boolean',
        // Customer settings sub-permissions
        'can_manage_general_customer_settings' => 'boolean',
        'can_manage_global_maintenance' => 'boolean',
        'can_manage_customer_maintenance' => 'boolean',
        // Notifications permission
        'can_manage_notifications' => 'boolean',
        // Reports tab permissions
        'can_view_postpay_report' => 'boolean',
        'can_view_daily_sms_report' => 'boolean',
        'can_view_money_transfer_report' => 'boolean',
        'can_view_monthly_sales_report' => 'boolean',
        'can_view_daemon_report' => 'boolean',
        'can_view_customer_rate' => 'boolean'
    ];

    /**
     * Get the user that owns the permissions.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all permission fields.
     */
    public static function getPermissionFields(): array
    {
        return [
            'can_view_dashboard',
            'can_view_customers',
            'can_view_customer_emails',
            'can_view_virtual_numbers',
            'can_view_reports',
            'can_view_settings',
            'can_manage_admin_users',
            'can_manage_contracts',
            'can_manage_cost',
            'can_view_customer_profile',
            'can_edit_customer_profile',
            'can_view_customer_keywords',
            'can_edit_customer_keywords',
            'can_view_customer_virtual_numbers',
            'can_edit_customer_virtual_numbers',
            'can_view_customer_notes',
            'can_edit_customer_notes',
            'can_view_customer_wallet',
            'can_edit_customer_wallet',
            'can_view_customer_logs',
            'can_view_customer_flag',
            'can_view_customer_reports',
            'can_create_customers',
            'can_delete_customers',
            'can_export_data',
            // Settings tab permissions
            'can_view_system_status',
            'can_view_system_logs',
            'can_view_user_activity',
            'can_view_env_variables',
            'can_manage_cache',
            'can_view_process_monitor',
            'can_view_queues',
            'can_manage_customer_settings',
            'can_manage_server_settings',
            'can_run_queries',
            'can_manage_dlr_update',
            'can_manage_global_pricing',
            // Customer settings sub-permissions
            'can_manage_general_customer_settings',
            'can_manage_global_maintenance',
            'can_manage_customer_maintenance',
            // Notifications permission
            'can_manage_notifications',
            // Reports tab permissions
            'can_view_postpay_report',
            'can_view_daily_sms_report',
            'can_view_money_transfer_report',
            'can_view_monthly_sales_report',
            'can_view_daemon_report',
            'can_view_customer_rate',
        ];
    }

    /**
     * Set all permissions to true.
     */
    public function grantAllPermissions(): void
    {
        foreach (self::getPermissionFields() as $field) {
            $this->$field = true;
        }
        $this->save();
    }

    /**
     * Set all permissions to false.
     */
    public function revokeAllPermissions(): void
    {
        foreach (self::getPermissionFields() as $field) {
            $this->$field = false;
        }
        $this->save();
    }
}
