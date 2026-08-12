<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItaggIpSecurity extends Model
{
    use HasFactory;

    protected $table = 'itagg_ipsecurity'; // Define the table name if it's different from the default

    // Specify the columns that are mass assignable
    protected $fillable = [
        'users_bigid',
        'ip',
    ];
}
