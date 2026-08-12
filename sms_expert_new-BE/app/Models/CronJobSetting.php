<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CronJobSetting extends Model
{
    use HasFactory;

    protected $table = 'cron_job_settings';

    protected $fillable = [
        'command',
        'name',
        'schedule',
        'enabled',
        'description',
        'last_toggled_at',
        'toggled_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_toggled_at' => 'datetime',
    ];

    /**
     * Check if a cron job is enabled
     *
     * @param string $command
     * @return bool
     */
    public static function isEnabled(string $command): bool
    {
        // Use cache for performance
        $cacheKey = 'cron_enabled_' . md5($command);
        
        return Cache::remember($cacheKey, 60, function () use ($command) {
            $setting = self::where('command', $command)->first();
            
            // If no setting exists, assume enabled
            if (!$setting) {
                return true;
            }
            
            return $setting->enabled;
        });
    }

    /**
     * Enable a cron job
     *
     * @param string $command
     * @param string|null $toggledBy
     * @return bool
     */
    public static function enable(string $command, ?string $toggledBy = null): bool
    {
        $setting = self::where('command', $command)->first();
        
        if (!$setting) {
            return false;
        }
        
        $setting->update([
            'enabled' => true,
            'last_toggled_at' => now(),
            'toggled_by' => $toggledBy,
        ]);
        
        // Clear cache
        Cache::forget('cron_enabled_' . md5($command));
        Cache::forget('cron_settings_all');
        
        return true;
    }

    /**
     * Disable a cron job
     *
     * @param string $command
     * @param string|null $toggledBy
     * @return bool
     */
    public static function disable(string $command, ?string $toggledBy = null): bool
    {
        $setting = self::where('command', $command)->first();
        
        if (!$setting) {
            return false;
        }
        
        $setting->update([
            'enabled' => false,
            'last_toggled_at' => now(),
            'toggled_by' => $toggledBy,
        ]);
        
        // Clear cache
        Cache::forget('cron_enabled_' . md5($command));
        Cache::forget('cron_settings_all');
        
        return true;
    }

    /**
     * Toggle a cron job
     *
     * @param string $command
     * @param string|null $toggledBy
     * @return array
     */
    public static function toggle(string $command, ?string $toggledBy = null): array
    {
        $setting = self::where('command', $command)->first();
        
        if (!$setting) {
            return ['success' => false, 'message' => 'Cron job not found'];
        }
        
        $newState = !$setting->enabled;
        
        $setting->update([
            'enabled' => $newState,
            'last_toggled_at' => now(),
            'toggled_by' => $toggledBy,
        ]);
        
        // Clear cache
        Cache::forget('cron_enabled_' . md5($command));
        Cache::forget('cron_settings_all');
        
        return [
            'success' => true,
            'enabled' => $newState,
            'message' => $newState ? 'Cron job enabled' : 'Cron job disabled',
        ];
    }

    /**
     * Get all cron job settings
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllSettings()
    {
        return Cache::remember('cron_settings_all', 60, function () {
            return self::all();
        });
    }

    /**
     * Sync settings from console.php
     * Creates missing entries
     *
     * @param array $cronJobs
     * @return void
     */
    public static function syncFromConfig(array $cronJobs): void
    {
        foreach ($cronJobs as $job) {
            self::firstOrCreate(
                ['command' => $job['command']],
                [
                    'name' => $job['name'],
                    'schedule' => $job['schedule'],
                    'enabled' => true,
                ]
            );
        }
        
        // Clear cache
        Cache::forget('cron_settings_all');
    }
}
