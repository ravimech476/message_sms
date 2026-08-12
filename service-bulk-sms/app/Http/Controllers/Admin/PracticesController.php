<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdatePracticesRequest;
use App\Http\Resources\PracticeResource;
use App\Models\Credential;
use App\Models\Provider;
use App\Models\Practice;

class PracticesController extends Controller
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $ccgList = Practice::whereHas('domain')->select('ccg_code', 'ccg_name')->groupBy('ccg_code', 'ccg_name')->orderBy('ccg_name')->pluck('ccg_name', 'ccg_code');
        $stpList = Practice::whereHas('domain')->select('stp_code', 'stp_name')->groupBy('stp_code', 'stp_name')->orderBy('stp_name')->pluck('stp_name', 'stp_code');
        $providerList = collect(config('messages.providers'))->pluck('name', 'driver');

        return view('admin.practices.index', compact( 'ccgList', 'stpList', 'providerList'));
    }

    public function update(BulkUpdatePracticesRequest $request)
    {
        $practices = Practice::whereHas('domain');

        if ($request->get('ccg')) {
            $practices->whereCCG($request->get('ccg'));
        } else {
            $practices->whereSTP($request->get('stp'));
        }

        $practices = $practices->get();

        foreach ($practices as $practice) {
            $domainProvider = $practice->domain->providers()->where('provider', $request->get('provider'))->first();

            if (!$domainProvider) {
                $domainProvider = $practice->domain->providers()->save(
                    new Provider([
                        'provider' => $request->get('provider'),
                    ])
                );
            }

            $practice->domain->providers()->update(['is_default' => 0]);
            $domainProvider->is_default = 1;
            $domainProvider->save();

            foreach ($request->get('credentials') as $credentialKey => $credentialValue) {
                $credential = Credential::firstOrNew([
                    'provider_id' => $domainProvider->id,
                    'key' => $credentialKey,
                ]);
                $credential->value = $credentialValue ?: '';
                $credential->save();
            }
        }

        return PracticeResource::collection($practices);
    }
}
