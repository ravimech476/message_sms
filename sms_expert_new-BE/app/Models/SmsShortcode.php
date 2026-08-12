<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsShortcode extends Model
{
    use HasFactory;

    protected $table = 'smsshortcodes';

    public $timestamps = false;

    protected $fillable = [
        'id','number', 'cost', 'adult', 'must_respond', 'can_send',
        'itagg_premiumroute', 'itagg_standardroute', 'module_restrict',
        'show_cp_subkeyword_management', 'content_type_restrict',
        'is_dedicated', 'shortcode_notes', 'whichoperator',
        'datelastsent', 'datenextsendallowed', 'orderdate',
        'mblox_retire_date', 'pooled'
    ];

}
