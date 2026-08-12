<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsgCampaign extends Model
{
    protected $table = 'smsg_campaigns';
    
    public $timestamps = false;
    
    protected $fillable = [
        'campaignid',
        'userref',
        'datetime',
        'campaignname',
        'filename',
        'numlines',
        'numlinesdone',
        'status',
        'statusinfo',
        'uniqueid',
        'dlrstatsdate',
        'dlrstats',
        'clickstatsdate',
        'clickstats',
        'canpausedelete'
    ];

    /**
     * Get the user that owns the campaign
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userref', 'bigid');
    }

    /**
     * Generate a unique campaign ID
     */
    public static function generateCampaignId($length = 5)
    {
        $allow = "abcdefghjkmnpqrstvwxyz23456789";
        
        do {
            $campaignId = '';
            for ($i = 0; $i < $length; $i++) {
                $campaignId .= $allow[rand(0, strlen($allow) - 1)];
            }
            
            $exists = self::where('campaignid', $campaignId)->exists();
        } while ($exists);
        
        return $campaignId;
    }

    /**
     * Generate a unique ID (32 characters)
     */
    public static function generateUniqueId($length = 32)
    {
        $allow = "abcdefghjkmnpqrstvwxyz23456789";
        $uniqueId = '';
        
        for ($i = 0; $i < $length; $i++) {
            $uniqueId .= $allow[rand(0, strlen($allow) - 1)];
        }
        
        return $uniqueId;
    }

    /**
     * Scope for non-deleted campaigns
     */
    public function scopeNotDeleted($query)
    {
        return $query->where('status', '!=', 'deleted');
    }

    /**
     * Scope for user campaigns
     */
    public function scopeForUser($query, $userref)
    {
        return $query->where('userref', $userref);
    }

    /**
     * Get formatted datetime
     */
    public function getFormattedDatetimeAttribute()
    {
        if (empty($this->datetime)) {
            return '';
        }
        
        $dt = $this->datetime;
        $year = substr($dt, 0, 4);
        $month = substr($dt, 4, 2);
        $day = substr($dt, 6, 2);
        $hour = substr($dt, 8, 2);
        $minute = substr($dt, 10, 2);
        $second = substr($dt, 12, 2);
        
        $timestamp = mktime($hour, $minute, $second, $month, $day, $year);
        
        $today = date("Ymd");
        $yesterday = date("Ymd", strtotime("yesterday"));
        $dateOnly = substr($dt, 0, 8);
        
        if ($dateOnly == $today) {
            return "Today, " . date("g:ia", $timestamp);
        } elseif ($dateOnly == $yesterday) {
            return "Yesterday, " . date("g:ia", $timestamp);
        } else {
            return date("jS M Y, g:ia", $timestamp);
        }
    }

    /**
     * Get status display text
     */
    public function getStatusDisplayAttribute()
    {
        switch ($this->status) {
            case 'filewaiting':
                return 'File queued for processing';
            case 'firing':
                return 'File being processed';
            case 'failed':
                return 'Incomplete/Failed';
            case 'deleted':
                return 'Deleted/Not Sent';
            case 'paused':
                return 'Paused';
            default:
                return 'Completed';
        }
    }

    /**
     * Check if campaign can be paused/deleted
     */
    public function getCanModifyAttribute()
    {
        return $this->canpausedelete === 'y';
    }
}
