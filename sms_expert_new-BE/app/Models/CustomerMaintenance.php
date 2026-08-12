<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerMaintenance extends Model
{
    use HasFactory;

    protected $table = 'customer_maintenance';

    protected $fillable = [
        'user_id',
        'user_bigid',
        'is_enabled',
        'maintenance_message',
        'start_time',
        'end_time',
        'created_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Get the customer user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who created this entry
     */
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if a specific customer is in maintenance mode
     */
    public static function isCustomerInMaintenance($userIdOrBigid): bool
    {
        // First check global maintenance
        $globalMaintenance = CustomerSetting::getValue('global_maintenance_mode', false);
        if ($globalMaintenance) {
            return true;
        }

        // Check customer-specific maintenance
        $query = self::where('is_enabled', true)
            ->where(function ($q) use ($userIdOrBigid) {
                $q->where('user_id', $userIdOrBigid)
                    ->orWhere('user_bigid', $userIdOrBigid);
            })
            ->where(function ($q) {
                $q->whereNull('start_time')
                    ->orWhere('start_time', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_time')
                    ->orWhere('end_time', '>=', now());
            });

        return $query->exists();
    }

    /**
     * Get maintenance message for a customer
     */
    public static function getMaintenanceMessage($userIdOrBigid = null): string
    {
        $defaultMessage = CustomerSetting::getValue('maintenance_message', 'The site is currently under maintenance. Please try again later.');

        if ($userIdOrBigid) {
            $maintenance = self::where('is_enabled', true)
                ->where(function ($q) use ($userIdOrBigid) {
                    $q->where('user_id', $userIdOrBigid)
                        ->orWhere('user_bigid', $userIdOrBigid);
                })
                ->first();

            if ($maintenance && $maintenance->maintenance_message) {
                return $maintenance->maintenance_message;
            }
        }

        return $defaultMessage;
    }

    /**
     * Scope for active maintenance
     */
    public function scopeActive($query)
    {
        return $query->where('is_enabled', true)
            ->where(function ($q) {
                $q->whereNull('start_time')
                    ->orWhere('start_time', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_time')
                    ->orWhere('end_time', '>=', now());
            });
    }
}
