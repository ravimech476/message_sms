<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCredentialsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'domain' => 'required',
            'credentials' => 'nullable|array',
            'provider' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $provider = config('messages.providers.' . $value);
                    if ($provider === null) {
                        $fail($value . ' is not a valid provider.');
                    }

                    $credentials = array_keys($this->credentials);
                    $required = $provider['required_credentials'];

                    if (count(array_diff($required, $credentials)) > 0) {
                        $fail($provider['name'] . ' requires the following credentials: ' . implode(', ', $required));
                    }
                },
            ],
        ];
    }
}
