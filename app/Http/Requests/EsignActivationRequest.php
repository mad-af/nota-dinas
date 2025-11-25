<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EsignActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        try {
            $nik = (string) $this->input('nik');
            $file = $this->file('signature');
            Log::debug('esign.activation.incoming', [
                'method' => $this->method(),
                'content_type' => $this->header('Content-Type'),
                'nik' => $nik,
                'nik_len' => strlen($nik),
                'has_signature' => $this->hasFile('signature'),
                'signature_name' => optional($file)->getClientOriginalName(),
                'signature_size' => optional($file)->getSize(),
                'user_id' => optional($this->user())->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('esign.activation.prepare_validation_failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'nik' => ['required', 'digits:16', Rule::unique(User::class)->ignore($this->user()->id)],
            'signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
