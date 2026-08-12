<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItaggOutboundBlacklist extends Model
{
    use HasFactory;

    protected $table = 'itagg_outbound_blacklist';

    // app/Models/ItaggOutboundBlacklist.php

    protected $casts = [
        'date_blocked' => 'datetime',
    ];

    // protected $fillable = [
    //     'phone_number',
    //     'status',
    // ];
}
