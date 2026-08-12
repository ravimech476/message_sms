<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'country';

    protected $fillable = [
        'name',
        'iso_code',
        'dialcode',
        'cost_per_sms',
        'cost_price',
        'cost_price_eur',
        'cost_price_gbp',
        'exchange_rate_eur_to_gbp',
        'exchange_rate_updated_at',
        'sinch_cost_price_eur',
        'sinch_cost_price_gbp',
        'sinch_price_updated_at',
        'price_update_mode',
    ];

    protected $casts = [
        'cost_per_sms' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'cost_price_eur' => 'decimal:4',
        'cost_price_gbp' => 'decimal:4',
        'exchange_rate_eur_to_gbp' => 'decimal:4',
        'sinch_cost_price_eur' => 'decimal:4',
        'sinch_cost_price_gbp' => 'decimal:4',
        'sinch_price_updated_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Get all user rates for this country
     */
    public function userRates()
    {
        return $this->hasMany(UserRate::class, 'country_id');
    }

    /**
     * Get all Vonage network costs for this country
     */
    public function vonageNetworkCosts()
    {
        return $this->hasMany(VonageNetworkCost::class, 'country_id');
    }
}
