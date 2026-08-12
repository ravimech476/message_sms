<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Legacy `product` catalogue. Maps a productref to its descriptions.
 * The invoice "summary" shown to customers is product.descriptionlong, exactly
 * like OLD SYSTEM generateInvoice.inc (joins orderitem.productref -> product).
 */
class Product extends Model
{
    protected $table = 'product';

    public $timestamps = false;

    /**
     * Resolve a productref to its descriptionlong, with a per-request cache so
     * the invoice LIST doesn't fire one query per row. Falls back to the OLD
     * SYSTEM default for the SMS credits product when the row is missing.
     */
    public static function summaryFor($productref): string
    {
        static $cache = [];

        $key = (string) ($productref ?? '');
        if ($key === '') {
            return 'Pre-purchase of SMS Expert Credits';
        }

        if (!array_key_exists($key, $cache)) {
            $cache[$key] = static::where('id', $productref)->value('descriptionlong')
                ?: 'Pre-purchase of SMS Expert Credits';
        }

        return $cache[$key];
    }
}
