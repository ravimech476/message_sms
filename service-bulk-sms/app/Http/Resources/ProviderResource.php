<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $requiredFields = array_fill_keys($this['required_credentials'], null);
        $hasCredentials = $request->domain && isset($this['credentials']) && count($this['credentials']) > 0;

        $data = [
            'name' => $this['name'],
            'driver' => $this['driver'],
            'is_default' => 1,
            'credentials' => $hasCredentials ? $this['credentials'] : $requiredFields,
        ];

        return $data;
    }
}
