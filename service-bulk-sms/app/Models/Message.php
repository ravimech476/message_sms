<?php

namespace App\Models;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
      'provider_id',
      'thread_id',
      'thread_item_id',
      'message_data',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function updates()
    {
        return $this->hasMany(MessageUpdate::class);
    }

    public function latestUpdate()
    {
        return $this->updates()->latest();
    }

    /**
     * Decrypt message data on save.
     *
     * @param $value
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function setMessageDataAttribute($value)
    {
        $key = Key::loadFromAsciiSafeString(config('messages.defuse_key'));
        $this->attributes['message_data'] = hex2bin(Crypto::encrypt(json_encode($value), $key, false));
    }

    /**
     * Decrypt message data on get.
     *
     * @return mixed|null
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function getMessageDataAttribute()
    {
        if ($this->attributes['message_data']) {
            $key = Key::loadFromAsciiSafeString(config('messages.defuse_key'));

            return json_decode(Crypto::decrypt(bin2hex($this->attributes['message_data']), $key, false));
        }

        return null;
    }
}
