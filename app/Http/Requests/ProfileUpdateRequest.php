<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'nik' => ['nullable', 'digits:16', Rule::unique(User::class)->ignore($this->user()->id)],
            'signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            //'jabatan' => ['required', 'string', 'max:255'],
            //'skpd_id' => ['required', 'exists:skpds,id'],
        ];
    }
}
