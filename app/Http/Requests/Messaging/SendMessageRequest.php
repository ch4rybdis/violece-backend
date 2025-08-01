<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\Dating\UserMatch;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $match = UserMatch::find($this->route('match')->id);

        if (!$match) {
            return false;
        }

        return $match->user1_id === Auth::id() || $match->user2_id === Auth::id();
    }

    public function rules(): array
    {
        return [
            'content' => 'required_without:meta|string|max:2000',
            'type' => 'sometimes|string|in:text,image,gif,audio,location',
            'meta' => 'required_if:type,image,gif,audio,location|nullable|array',
            'meta.url' => 'required_if:type,image,gif,audio|nullable|string|max:500',
            'meta.width' => 'nullable|integer',
            'meta.height' => 'nullable|integer',
            'meta.duration' => 'nullable|integer',
            'meta.latitude' => 'required_if:type,location|nullable|numeric|between:-90,90',
            'meta.longitude' => 'required_if:type,location|nullable|numeric|between:-180,180',
            'meta.place_name' => 'nullable|string|max:255',
        ];
    }
}
