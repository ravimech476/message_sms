<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersSessionLog extends Model
{
    protected $table = 'users_session_logs';

    public $timestamps = false;

    protected $fillable = [
        'big_id',
        'ip_address',
        'itaggcustid',
        'status',
        'login_date',
        'logout_date',
        'force_logout',
        'force_logout_by_ip',
        'force_logout_by',
        'force_logout_date',
    ];
}
