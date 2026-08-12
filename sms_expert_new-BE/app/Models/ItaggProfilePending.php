<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItaggProfilePending extends Model
{
    use HasFactory;

    protected $table = 'itagg_profilepending'; // Define the table name if it's different from the default

    // Specify the columns that are mass assignable
    protected $fillable = [
        'id',
        'bigid',
        'busname',
        'contactname',
        'address1',
        'address2',
        'town',
        'city',
        'county',
        'pcode',
        'country',
        'mobilenumber',
        'phone',
        'contactemail',
        'pword',
        'ips',
        'explanation',
    ];
}
