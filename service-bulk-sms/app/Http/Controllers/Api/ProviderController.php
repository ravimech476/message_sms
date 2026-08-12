<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetProviderRequest;
use App\Http\Requests\BulkUpdatePracticesRequest;
use App\Http\Requests\UpdateCredentialsRequest;
use App\Http\Resources\PracticeResource;
use App\Http\Resources\ProviderResource;
use App\Models\Credential;
use App\Models\Provider;

class ProviderController extends Controller
{
    /**
     * Get all providers with their credentials.
     *
     * @param GetProviderRequest $providerRequest
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function get(GetProviderRequest $providerRequest)
    {
        $providers = collect(config('messages.providers'))
            ->sortBy(function ($provider) {
                return $provider['name'];
            });

        $additional['meta']['update'] = route('api.providers.update');

        // Add stored credentials if request contains domain
        if ($providerRequest->get('domain')) {
            $providers = $providers->map(function($provider) use($providerRequest){
                $domainProvider = $providerRequest->get('practice')->domain->providers()->where('provider', $provider['driver'])->first();
                $hasCredentials = $domainProvider && $domainProvider->credentials;
                $provider['credentials'] =  $hasCredentials ? $domainProvider->credentials->pluck('value', 'key')->toArray() : [];

                return $provider;
            });
            $additional['meta']['domain'] = $providerRequest->get('domain');
        }

        return ProviderResource::collection($providers)->additional($additional);
    }

    /**
     * Update credentials.
     *
     * @param UpdateCredentialsRequest $request
     * @return PracticeResource
     */
    public function update(UpdateCredentialsRequest $request)
    {
        $practice = $request->get('practice');
        $domain = $practice->domain;

        $domainProvider = $request->has('provider') ? $domain->providers()->where('provider', $request->get('provider'))->first() : null;

        if ($request->get('provider') && !$domainProvider) {
            $domainProvider = $domain->providers()->save(
                new Provider([
                    'provider' => $request->get('provider'),
                    'is_default' => $request->has('make_default') ? 1 : 0,
                ])
            );
        }

        $updateDefault = $request->has('make_default') && $request->get('provider') !== $domain->default_driver;

        if ($updateDefault || empty($request->get('provider'))) {
            $domain->providers()->update(['is_default' => 0]);

            if ($updateDefault && !empty($request->get('provider'))) {
                $domainProvider->is_default = 1;
                $domainProvider->save();
            }
        }

        if ($request->get('provider') && $request->get('credentials')) {
            foreach ($request->get('credentials') as $credentialKey => $credentialValue) {
                $credential = Credential::firstOrNew([
                    'provider_id' => $domainProvider->id,
                    'key' => $credentialKey,
                ]);
                $credential->value = $credentialValue ?: '';
                $credential->save();
            }
        }

        return new PracticeResource($practice);
    }
}
