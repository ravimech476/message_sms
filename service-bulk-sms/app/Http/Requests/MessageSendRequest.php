<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MessageSendRequest extends FormRequest
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
        // @TODO: We're not validating to phone number, but if the sending fails
        // because the provider rejects the delivery it will automatically
        // fallback to email (if it's passed)

        return [
            'domain' => 'required',
            'to' => 'required|numeric',
            'message' => 'required',
            'thread_id' => 'numeric',
            'thread_item_id' => 'numeric',
            'fallback' => 'nullable|array',
        ];
    }
}
