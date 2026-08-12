<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VonageNetworkCost extends Model
{
    use HasFactory;

    protected $table = 'vonage_network_costs';

    protected $fillable = [
        'country_id',
        'country_code',
        'country_name',
        'network_code',
        'network_name',
        'cost_price_gbp',
        'cost_price_eur',
        'cost_price_usd',
        'is_active',
    ];

    protected $casts = [
        'cost_price_gbp' => 'decimal:6',
        'cost_price_eur' => 'decimal:6',
        'cost_price_usd' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    /**
     * Get the country that owns this network cost
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    /**
     * Scope to get only active network costs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by country code
     */
    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    /**
     * Scope to filter by network code
     */
    public function scopeByNetwork($query, $networkCode)
    {
        return $query->where('network_code', $networkCode);
    }
}
