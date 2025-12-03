<?php

namespace App\Services;

use App\Jobs\LogApiCall;
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

    protected function maskPayload(array $payload): array
    {
        $masked = $payload;
        $keys = ['passphrase', 'totp', 'pdfPassword', 'file', 'signatureProperties.imageBase64'];
        foreach ($keys as $k) {
            $this->applyMask($masked, explode('.', $k));
        }

        return $masked;
    }

    protected function applyMask(&$node, array $path): void
    {
        if (empty($path)) {
            return;
        }
        $key = array_shift($path);
        if (! is_array($node)) {
            return;
        }
        if (array_key_exists($key, $node)) {
            if (empty($path)) {
                if ($node[$key] !== null) {
                    $node[$key] = '***';
                }

                return;
            }
            $this->applyMask($node[$key], $path);

            return;
        }
        foreach ($node as &$child) {
            $this->applyMask($child, array_merge([$key], $path));
        }
        unset($child);
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
        $maskedPayload = $this->maskPayload($payload);
        $maskedResponse = $this->maskResponseBody($responseBody);

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

    protected function maskResponseBody($body): string
    {
        $s = (string) $body;
        $arr = json_decode(json: $s, associative: true);
        if (! is_array($arr)) {
            return mb_strimwidth($s, 0, 5000, '...');
        }
        $this->maskResponseFiles($arr);
        $encoded = json_encode($arr, JSON_UNESCAPED_UNICODE);

        return mb_strimwidth((string) $encoded, 0, 5000, '...');
    }

    protected function maskResponseFiles(&$node): void
    {
        if (! is_array($node)) {
            return;
        }
        if (array_key_exists('file', $node)) {
            if (is_array($node['file'])) {
                foreach ($node['file'] as $i => $v) {
                    $node['file'][$i] = '***';
                }
            } elseif ($node['file'] !== null) {
                $node['file'] = '***';
            }
        }
        foreach ($node as &$child) {
            if (is_array($child)) {
                $this->maskResponseFiles($child);
            }
        }
        unset($child);
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
