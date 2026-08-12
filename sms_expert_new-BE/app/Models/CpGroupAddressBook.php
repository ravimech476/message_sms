<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CpGroupAddressBook extends Model
{
    use HasFactory;

    protected $table = 'cp_group_addressbook';

    public $timestamps = false;

    protected $fillable = ['group_id', 'addressbook_id'];

    public function addressBook()
    {
        return $this->belongsTo(CpUsersAddressBook::class, 'addressbook_id', 'id');
    }
}
