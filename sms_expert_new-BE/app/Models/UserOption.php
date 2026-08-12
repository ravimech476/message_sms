<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOption extends Model
{
    use HasFactory;

    protected $table = 'useroption'; // Ensure this matches your table name
    protected $primaryKey = 'userref'; // Set this to your actual primary key if it's not 'id'
    public $incrementing = false; // Set to true if your primary key is an auto-incrementing integer
    public $timestamps = false;
    protected $fillable = [
        'userref', 'immediateEmailReminderon', 'immediateOutOfFundsNotificationEmail','explanation','stop_command_url',
        'stopcommand_contactemail',
        'stopcommand_contactname',
        'clientcommfail','profileupdate_lockout',
        'agreedcontracts',
        'agreedcontracts_description',
        'customer_tour_completed',
        'campaign_tour_completed',
        'customer_tour_completed_at',
        'campaign_tour_completed_at'
    ];
   
}
