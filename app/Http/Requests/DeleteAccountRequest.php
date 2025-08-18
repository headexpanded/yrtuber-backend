<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use JetBrains\PhpStorm\ArrayShape;

class DeleteAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    #[ArrayShape(['password' => "string"])] public function rules(): array
    {
        return [
            'password' => 'required|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    #[ArrayShape(['password.required' => "string", 'password.string' => "string"])] public function messages(): array
    {
        return [
            'password.required' => 'Password is required to delete your account.',
            'password.string' => 'Password must be a string.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  Validator  $validator
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();

            if (!$user) {
                $validator->errors()->add('user', 'User not found.');
                return;
            }

            if (!Hash::check($this->password, $user->password)) {
                $validator->errors()->add('password', 'The provided password is incorrect.');
            }
        });
    }
}
