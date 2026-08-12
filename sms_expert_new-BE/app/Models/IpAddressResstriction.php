<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpAddressResstriction extends Model
{
    public $timestamps = false;

    protected $table = 'ip_address_resstrictions';

    protected $fillable = [
        'ip_address',
        'created_by',
        'created_by_ip',
        'created',
        'modified_by',
        'modified',
        'status',
        'bigid',
    ];
}
