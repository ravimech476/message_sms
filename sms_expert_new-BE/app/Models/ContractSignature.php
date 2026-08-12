<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contract_id',
        'signee_name',
        'signee_email',
        'signee_position',
        'signature_data',
        'signature_image',
        'ip_address',
        'user_agent',
        'signed_at',
        'signed_via',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    /**
     * Get the user that signed the contract
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contract that was signed
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get signature image URL
     */
    public function getSignatureImageUrl()
    {
        if ($this->signature_image) {
            return asset('storage/' . $this->signature_image);
        }
        return null;
    }

    /**
     * Check if signature has an image
     */
    public function hasSignatureImage()
    {
        return !empty($this->signature_image);
    }
}
