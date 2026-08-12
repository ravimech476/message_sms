<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;
    
    protected $table = 'orderitem';
    
    public $timestamps = false;

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoiceref', 'id');
    }

    /**
     * Invoice summary = product.descriptionlong for this line's productref
     * (OLD SYSTEM parity). e.g. 20551 -> "Pre-purchase of SMS Expert Credits",
     * 51930 -> "SMS Expert Services".
     */
    public function getSummaryAttribute(): string
    {
        return Product::summaryFor($this->productref ?? null);
    }
}
