<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Practice extends Model
{
    protected $connection = 'crm';
    protected $table = 'nhs_gp_prac_map';
    protected $appends = [
        'edit_link',
    ];

    /**
     * Domain details of a practice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function domain()
    {
        return $this->hasOne(Domain::class, 'ods_code', 'ods_code');
    }

    /**
     * Search practice by Organisation Data Service (ODS) code.
     *
     * @param Builder $query
     * @param $odsCode
     * @return Builder
     */
    public function scopeWhereODS(Builder $query, $odsCode)
    {
        return $query->where('ods_code', $odsCode);
    }

    /**
     * Search practice by Clinical Commissioning Group (CCG) code.
     *
     * @param Builder $query
     * @param $ccgCode
     * @return Builder
     */
    public function scopeWhereCCG(Builder $query, $ccgCode)
    {
        return $query->where('ccg_code', $ccgCode);
    }

    /**
     * Search practice by Sustainability and Transformation Partnership (STP) code.
     *
     * @param Builder $query
     * @param $stpCode
     * @return Builder
     */
    public function scopeWhereSTP(Builder $query, $stpCode)
    {
        return $query->where('stp_code', $stpCode);
    }

    public function getEditLinkAttribute()
    {
        return route('practices.edit', $this);
    }
}
