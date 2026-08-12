<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'title',
        'content',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'type',
        'is_active',
        'requires_signature',
        'version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_signature' => 'boolean',
        'version' => 'integer',
        'file_size' => 'integer',
    ];

    /**
     * Get the customer this contract is assigned to (legacy single customer)
     * Returns null if contract is for all customers
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get all customers assigned to this contract (many-to-many)
     */
    public function customers()
    {
        return $this->belongsToMany(User::class, 'contract_customer', 'contract_id', 'customer_id')
                    ->withTimestamps();
    }

    /**
     * Get all signatures for this contract
     */
    public function signatures()
    {
        return $this->hasMany(ContractSignature::class);
    }

    /**
     * Check if a user has signed this contract
     */
    public function isSignedByUser($userId)
    {
        return $this->signatures()->where('user_id', $userId)->exists();
    }

    /**
     * Get signature by user
     */
    public function getUserSignature($userId)
    {
        return $this->signatures()->where('user_id', $userId)->first();
    }

    /**
     * Check if contract has an uploaded file
     */
    public function hasFile()
    {
        return !empty($this->file_path) && !empty($this->file_name);
    }

    /**
     * Get the full URL to the contract file
     */
    public function getFileUrl()
    {
        if (!$this->hasFile()) {
            return null;
        }
        return asset('storage/' . $this->file_path);
    }

    /**
     * Get human readable file size
     */
    public function getFileSizeFormatted()
    {
        if (!$this->file_size) {
            return null;
        }
        
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if contract is for all customers
     * (customer_id is null AND no specific customers in pivot table)
     */
    public function isForAllCustomers()
    {
        return is_null($this->customer_id) && $this->customers()->count() === 0;
    }

    /**
     * Check if contract is for specific customer
     */
    public function isForCustomer($customerId)
    {
        // Check legacy field
        if ($this->customer_id == $customerId) {
            return true;
        }
        
        // Check many-to-many relationship
        if ($this->customers()->where('users.id', $customerId)->exists()) {
            return true;
        }
        
        // Check if it's for all customers
        if ($this->isForAllCustomers()) {
            return true;
        }
        
        return false;
    }

    /**
     * Scope for active contracts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for main contracts
     */
    public function scopeMain($query)
    {
        return $query->where('type', 'main');
    }

    /**
     * Scope for addendums
     */
    public function scopeAddendum($query)
    {
        return $query->where('type', 'addendum');
    }

    /**
     * Scope for privacy policy
     */
    public function scopePrivacyPolicy($query)
    {
        return $query->where('type', 'privacy_policy');
    }

    /**
     * Scope for contracts available to a specific customer
     * Includes:
     * 1. "All customers" contracts (customer_id is null AND no pivot entries)
     * 2. Legacy customer-specific contracts (customer_id matches)
     * 3. New many-to-many customer-specific contracts (exists in pivot table)
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where(function($q) use ($customerId) {
            // 1. Contract is for ALL customers (customer_id null AND no specific customers assigned)
            $q->where(function($allCustomers) {
                $allCustomers->whereNull('customer_id')
                             ->whereDoesntHave('customers');
            })
            // 2. Legacy: contract assigned to this specific customer via customer_id field
            ->orWhere('customer_id', $customerId)
            // 3. New: contract assigned to this customer via pivot table
            ->orWhereHas('customers', function($pivot) use ($customerId) {
                $pivot->where('users.id', $customerId);
            });
        });
    }

    /**
     * Scope for contracts assigned to all customers
     * (customer_id is null AND no entries in pivot table)
     */
    public function scopeForAllCustomers($query)
    {
        return $query->whereNull('customer_id')
                     ->whereDoesntHave('customers');
    }

    /**
     * Scope for contracts assigned to specific customers only
     * (either via legacy customer_id OR via pivot table)
     */
    public function scopeForSpecificCustomer($query)
    {
        return $query->where(function($q) {
            $q->whereNotNull('customer_id')
              ->orWhereHas('customers');
        });
    }
}
