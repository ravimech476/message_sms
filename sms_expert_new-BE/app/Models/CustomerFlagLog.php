<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerFlagLog extends Model
{
    protected $fillable = [
        'customer_id',
        'bigid',
        'old_flag',
        'new_flag',
        'changed_by',
        'changed_ip',
        'changed_at'
    ];
}
