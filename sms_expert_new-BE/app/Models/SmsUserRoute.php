<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsUserRoute extends Model
{
    use HasFactory;

    protected $table = 'smsg_userroute';

    protected $fillable = [
        'userref',
        'username',
        'routenum',
        'countrydialcode',
        'numbits',
        'origtype',
        'userprice',
        'priority'
    ];

    public $timestamps = false;
}
