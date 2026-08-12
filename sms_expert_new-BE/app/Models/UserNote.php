<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNote extends Model
{
    use HasFactory;

    protected $table = 'users_notes';

    protected $fillable = [
        'users_bigid',
        'notes',
        'staffinitials',
        'nextcontactdate',
        'timelength',
        'insertdate',
        'myinsertdate',
        'settonousrprenfc'
    ];

    public $timestamps = false;
}

