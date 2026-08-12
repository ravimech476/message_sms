<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $connection = 'mysql';
    protected $table = 'providers';

    protected $fillable = [
      'provider',
      'is_default',
      'sender_identifier',
    ];

    protected $appends = [
      'provider',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Override database name to make whereHas work with multiple databases
        $this->setTable($this->getConnection()->getDatabaseName() . '.' .$this->getTable());
    }

    /**
     * Domain details.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    /**
     * Provider details.
     *
     * @return array
     */
    public function getProviderAttribute()
    {
        return config('messages.providers.' . $this->attributes['provider']);
    }

    /**
     * Available credentials for a domain.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function credentials()
    {
        return $this->hasMany(Credential::class, 'provider_id');
    }
}
