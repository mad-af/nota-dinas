<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EsignActivationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nik' => ['required', 'digits:16', Rule::unique(User::class)->ignore($this->user()->id)],
            'signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}