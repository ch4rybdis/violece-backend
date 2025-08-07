<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'content' => 'required_if:type,text|nullable|string|max:1000',
            'type' => 'required|in:text,image,gif,audio,location',
            'meta' => 'nullable|array',
            'meta.file_url' => 'required_if:type,image,gif,audio|nullable|url',
            'meta.file_size' => 'nullable|integer|max:10485760', // 10MB
            'meta.duration' => 'required_if:type,audio|nullable|integer|max:300', // 5 minutes
            'meta.latitude' => 'required_if:type,location|nullable|numeric',
            'meta.longitude' => 'required_if:type,location|nullable|numeric',
            'meta.location_name' => 'nullable|string|max:200'
        ];
    }

    public function messages()
    {
        return [
            'content.required_if' => 'Message content is required for text messages',
            'meta.file_url.required_if' => 'File URL is required for media messages',
            'meta.duration.required_if' => 'Duration is required for audio messages',
            'meta.latitude.required_if' => 'Latitude is required for location messages',
            'meta.longitude.required_if' => 'Longitude is required for location messages'
        ];
    }
}
