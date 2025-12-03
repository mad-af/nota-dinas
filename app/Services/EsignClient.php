<?php

namespace App\Services;

use App\Jobs\LogApiCall;
use App\Services\Esign\Support\PayloadMasker;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

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

    protected function requestWithLog(string $endpoint, string $method, array $payload): Response
    {
        $correlationId = (string) Str::uuid();
        $userId = Auth::id();
        $t0 = microtime(true);
        $resp = null;
        $error = null;
        try {
            if ($method === 'POST') {
                $resp = $this->client()->post($this->baseUrl().$endpoint, $payload);
            } elseif ($method === 'GET') {
                $resp = $this->client()->get($this->baseUrl().$endpoint, $payload);
            } else {
                $resp = $this->client()->send($method, $this->baseUrl().$endpoint, ['json' => $payload]);
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $resp = new Response(new \GuzzleHttp\Psr7\Response(500));
        }
        $elapsedMs = (int) round((microtime(true) - $t0) * 1000);

        $statusCode = $resp->status();
        $responseBody = $resp->body();
        $maskedPayload = PayloadMasker::maskRequest($payload);
        $maskedResponse = PayloadMasker::maskResponse((string) $responseBody);

        LogApiCall::dispatch([
            'correlation_id' => $correlationId,
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'request_payload' => $maskedPayload,
            'response_body' => $maskedResponse,
            'duration_ms' => $elapsedMs,
            'error_message' => $error,
        ]);

        return $resp;
    }

    public function signPdf(array $payload): Response
    {
        return $this->requestWithLog('/api/v2/sign/pdf', 'POST', $payload);
    }

    public function getTotp(array $payload): Response
    {
        return $this->requestWithLog('/api/v2/sign/get/totp', 'POST', $payload);
    }

    public function requestTotp(array $payload): Response
    {
        return $this->getTotp($payload);
    }

    public function sealPdf(array $payload): Response
    {
        return $this->requestWithLog('/api/v2/seal/pdf', 'POST', $payload);
    }

    public function verifyPdf(array $payload): Response
    {
        return $this->requestWithLog('/api/v2/verify/pdf', 'POST', $payload);
    }

    public function checkUserStatus(array $payload): Response
    {
        return $this->requestWithLog('/api/v2/user/check/status', 'POST', $payload);
    }

    public function getSealActivation(array $payload): Response
    {
        return $this->requestWithLog('/api/v2/seal/get/activation', 'POST', $payload);
    }

    public function getSealTotp(array $payload): Response
    {
        return $this->requestWithLog('/api/v2/seal/get/totp', 'POST', $payload);
    }
}
