<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserReminder extends Model
{
    use HasFactory;

    protected $table = 'userreminder'; // Ensure this matches your table name
    protected $primaryKey = 'usersbigidref'; // Set this to your actual primary key if it's not 'id'
    public $incrementing = false; // Set to true if your primary key is an auto-incrementing integer
    public $timestamps = false;
    protected $fillable = [
        'usersbigidref', 'reminderon', 'numonremind', 'reminderperiod', 'lastsent'
    ];
}
