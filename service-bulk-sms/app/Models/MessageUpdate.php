<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageUpdate extends Model
{
    protected $fillable = [
      'message_id',
      'delivery_type',
      'status',
      'status_note',
      'supplier_message_id',
      'cost_per_sms',
      'delivered_at',
    ];

    protected $casts = [
      'delivered_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
