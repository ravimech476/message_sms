<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CpUsersGroup extends Model
{
    use HasFactory;

    protected $table = 'cp_users_groups';

    protected $fillable = ['name','user_bigid'];

    public $timestamps = false;

    public function groupAddressBooks()
    {
        return $this->hasMany(CpGroupAddressBook::class, 'group_id', 'id');
    }
}
