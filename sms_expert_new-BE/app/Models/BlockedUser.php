<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedUser extends Model
{
    protected $table = 'blocked_users';

    public $timestamps = false;

    protected $fillable = [
        'users_session_logs_id',
        'itaggcustid',
        'ip_address',
        'big_id',
        'status',
        'blocked_date',
        'blocked_by_ip',
        'blocked_by',
    ];
}


