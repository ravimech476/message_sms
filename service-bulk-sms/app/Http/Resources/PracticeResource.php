<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PracticeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'practice_name' => $this->practice_name,
            'ods' => $this->ods_code,
            'pcn' => [
                'name' => $this->pcn_name,
                'code' => $this->pcn_code,
            ],
            'ccg' => [
                'name' => $this->ccg_name,
                'code' => $this->ccg_code,
            ],
            'stp' => [
                'name' => $this->stp_name,
                'code' => $this->stp_code,
            ],
        ];

        if ($this->domain) {
            $data['domain'] = $this->domain->domain;
            $data['sp_directory'] = $this->domain->sp_directory;
            $data['sp_server'] = $this->domain->sp_server;
            $data['provider'] = null;

            $defaultProvider = $this->domain->defaultProvider;
            if ($defaultProvider) {
                $data['provider'] = [
                    'name' => $defaultProvider->provider['name'],
                    'driver' => $defaultProvider->provider['driver'],
                    'required_credentials' => $defaultProvider->provider['required_credentials'],
                ];
            }

            if ($this->domain->providers) {
                $credentials = [];
                foreach ($this->domain->providers as $provider) {
                    $credentials[$provider->provider['driver']] = $provider->credentials->pluck('value', 'key');
                }
                $data['credentials'] = count($credentials) > 0 ? $credentials : null;
            }
        }

        return $data;
    }
}
