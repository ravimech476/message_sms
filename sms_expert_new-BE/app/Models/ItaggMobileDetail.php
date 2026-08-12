<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItaggMobileDetail extends Model
{
    use HasFactory;

    protected $table = 'itagg_mobiledetail';
    
    public $timestamps = false;

    protected $fillable = [
        'msisdn',
        'net_id',
        'confirmed',
        'lastchanged',
        'mbloxDeliverer'
    ];

    /**
     * Get all address book entries for this mobile
     */
    public function addressBooks()
    {
        return $this->hasMany(CpUsersAddressBook::class, 'itagg_mobiledetail_id');
    }

    /**
     * Get the network that owns the mobile detail
     */
    public function network()
    {
        return $this->belongsTo(MobNetwork::class, 'net_id', 'id');
    }
}
