<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;



class ItaggInstance extends Model
{
    use HasFactory;

    protected $table = 'itagg_instance';
    
    public $timestamps = false;

    protected $fillable = [
        'id',
        'users_bigid',
        'keyword',
        'purchased',
        'expiry',
        'nextcontactaboutrenewal',
        'keylevel',
        'forwarding_email',
        'response_sender_id',
        'response_content',
        'smsshortcodes_id',
        'itagg_type_id',
        'max_subkeywords',
        'itagg_purchasetype_id',
        'active',
        'modules_enabled',
        'module_restrict',
        'status'
    ];

    public function smsshortcode()
    {
        return $this->belongsTo(SmsShortcode::class, 'smsshortcodes_id');
    }
}
