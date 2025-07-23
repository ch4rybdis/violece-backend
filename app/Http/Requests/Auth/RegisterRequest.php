<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:' . now()->subYears(18)->format('Y-m-d')],
            'gender' => ['required', 'integer', 'in:1,2,3'],
            'preference_gender' => ['required', 'array'],
            'preference_gender.*' => ['integer', 'in:1,2,3'],

            // Email OR Phone required (not both)
            'email' => ['required_without:phone', 'email', 'unique:users,email'],
            'phone' => ['required_without:email', 'string', 'unique:users,phone'],

            'password' => ['required', 'confirmed', Password::defaults()],

            // Location (optional at registration)
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'birth_date.before' => 'You must be at least 18 years old to use Violece.',
            'email.required_without' => 'Either email or phone number is required.',
            'phone.required_without' => 'Either email or phone number is required.',
            'gender.in' => 'Please select a valid gender option.',
            'preference_gender.*.in' => 'Invalid preference selection.',
        ];
    }
}
