<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';
     public $timestamps = false;
    public function orderItems()
    {
        return $this->hasOne(OrderItem::class, 'invoiceref', 'id');
    }

    /**
     * Invoice summary = product.descriptionlong of this invoice's order item
     * (OLD SYSTEM parity — generateInvoice.inc). Falls back to the SMS-credits
     * default when no order item / product is found.
     */
    public function getSummaryAttribute(): string
    {
        $productref = optional($this->orderItems)->productref;
        return Product::summaryFor($productref);
    }
}
