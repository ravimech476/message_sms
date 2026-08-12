<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $table = 'email_logs';

    protected $fillable = [
        'to',
        'subject',
        'message',
        'sent_at',
        'status',
        'created_at',
    ];

    public $timestamps = false;
}
