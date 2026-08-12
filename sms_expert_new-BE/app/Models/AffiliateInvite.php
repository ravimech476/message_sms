<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateInvite extends Model
{
    use HasFactory;

    protected $table = 'affiliateinvite';
    public $timestamps = false;

    protected $fillable = [
        'assigned_userref',
        'icode',
        'codenote',
        'subdomain',
    ];
   
}
