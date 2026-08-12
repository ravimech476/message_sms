<?php

namespace App\Models;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Illuminate\Database\Eloquent\Model;

class Credential extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'provider_id',
        'key',
        'value',
    ];

    public function domainProvider()
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Encrypt value on save.
     *
     * @param $value
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function setValueAttribute($value)
    {
        $key = Key::loadFromAsciiSafeString(config('messages.defuse_key'));
        $this->attributes['value'] = hex2bin(Crypto::encrypt($value, $key, false));
    }

    /**
     * Decrypt value on get.
     *
     * @return mixed|string
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException
     */
    public function getValueAttribute()
    {
        if ($this->attributes['value']) {
            $key = Key::loadFromAsciiSafeString(config('messages.defuse_key'));

            return Crypto::decrypt(bin2hex($this->attributes['value']), $key, false);
        }

        return null;
    }
}
