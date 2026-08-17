<?php

namespace App\Models;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Illuminate\Database\Eloquent\Model;

/**
 * @author Anand Karthik (modified — tolerant decrypt: a bad credential no longer 500s the page)
 */
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
        if (empty($this->attributes['value'])) {
            return null;
        }

        try {
            $key = Key::loadFromAsciiSafeString(config('messages.defuse_key'));

            return Crypto::decrypt(bin2hex($this->attributes['value']), $key, false);
        } catch (\Throwable $e) {
            // A single unreadable credential (legacy/plaintext/key-mismatch) must not
            // 500 the whole practices listing — surface it as null and move on.
            \Log::warning('Credential decrypt failed for id ' . ($this->attributes['id'] ?? '?') . ': ' . $e->getMessage());

            return null;
        }
    }
}
