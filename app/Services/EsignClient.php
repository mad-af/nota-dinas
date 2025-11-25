<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class EsignClient
{
    protected function baseUrl(): string
    {
        return (string) config('services.esign.url');
    }

    protected function client()
    {
        $http = Http::withBasicAuth((string) config('services.esign.user'), (string) config('services.esign.pass'))
            ->acceptJson()
            ->asJson();

        return $http;
    }

    public function signPdf(array $payload): Response
    {
        return $this->client()->post($this->baseUrl().'/api/v2/sign/pdf', $payload);
    }

    public function getTotp(array $payload): Response
    {
        return $this->client()->post($this->baseUrl().'/api/v2/sign/get/totp', $payload);
    }

    public function requestTotp(array $payload): Response
    {
        return $this->getTotp($payload);
    }

    public function sealPdf(array $payload): Response
    {
        return $this->client()->post($this->baseUrl().'/api/v2/seal/pdf', $payload);
    }

    public function verifyPdf(array $payload): Response
    {
        return $this->client()->post($this->baseUrl().'/api/v2/verify/pdf', $payload);
    }

    public function checkUserStatus(array $payload): Response
    {
        return $this->client()->post($this->baseUrl().'/api/v2/user/check/status', $payload);
    }

    public function getSealActivation(array $payload): Response
    {
        return $this->client()->post($this->baseUrl().'/api/v2/seal/get/activation', $payload);
    }

    public function getSealTotp(array $payload): Response
    {
        return $this->client()->post($this->baseUrl().'/api/v2/seal/get/totp', $payload);
    }
}
