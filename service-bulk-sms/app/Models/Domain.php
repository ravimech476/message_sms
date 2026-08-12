<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $connection = 'crm';
    protected $table = 'sp_domain_ods_map';
    protected $appends = [
      'default_driver'
    ];

    /**
     * Practice details of a domain.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function practice()
    {
        return $this->belongsTo(Practice::class, 'ods_code', 'ods_code');
    }

    public function providers()
    {
        return $this->hasMany(Provider::class, 'domain_id', 'id');
    }

    public function defaultProvider()
    {
        return $this->hasOne(Provider::class, 'domain_id', 'id')->where('is_default', 1);
    }

    public function getDefaultDriverAttribute()
    {
        return $this->defaultProvider() ? $this->defaultProvider()->value('provider')['driver'] : null;
    }

    public function credentials()
    {
        return $this->hasManyThrough(Credential::class, Provider::class, 'domain_id', 'provider_id', 'id', 'id');
    }
}
