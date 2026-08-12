<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpUsersAddressBook extends Model
{
    protected $table = 'cp_users_addressbook';

    protected $fillable = ['name','itagg_mobiledetail_id','user_bigid','is_favourite'];

    public $timestamps = false;

    public function mobileDetail()
    {
        return $this->belongsTo(ItaggMobileDetail::class, 'itagg_mobiledetail_id', 'id');
    }
}
