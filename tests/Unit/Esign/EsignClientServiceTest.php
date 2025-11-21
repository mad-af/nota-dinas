<?php

// File: tests/Unit/Esign/EsignClientServiceTest.php

namespace Tests\Unit\Esign;

use App\Services\EsignClient;
use Illuminate\Support\Facades\Http;
use Tests\_support\CreatesBase64Pdf;
use Tests\TestCase;

/**
 * Spec: file:///mnt/data/pdf-petunjuk-teknis-api-esign-client-service-v221-sign-2_compress.pdf
 */
class EsignClientServiceTest extends TestCase
{
    use CreatesBase64Pdf;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.esign.url' => 'https://example.test',
            'services.esign.user' => 'u',
            'services.esign.pass' => 'p',
            'services.esign.client_ip_whitelist' => '1.2.3.4',
        ]);
    }

    public function test_sign_pdf_with_passphrase_sends_basic_auth_and_body_shape(): void
    {
        Http::fake([
            '*/api/v2/sign/pdf' => Http::response(['file' => [$this->makeBase64Pdf()], 'message' => 'success'], 200),
        ]);

        $client = new EsignClient;
        $payload = [
            'nik' => '1234567890123456',
            'passphrase' => 'secret',
            'signatureProperties' => [[
                'tampilan' => 'VIS', 'page' => 1, 'originX' => 0, 'originY' => 0, 'width' => 100, 'height' => 50,
            ]],
            'file' => [$this->makeBase64Pdf()],
        ];
        $client->signPdf($payload);

        Http::assertSent(function ($request) {
            $headers = $request->headers();
            $hasAuth = array_key_exists('Authorization', $headers);
            $hasIpHeader = array_key_exists('X-Forwarded-For', $headers) && (
                is_array($headers['X-Forwarded-For']) ? in_array('1.2.3.4', $headers['X-Forwarded-For']) : $headers['X-Forwarded-For'] === '1.2.3.4'
            );
            $body = $request->data();

            return str_starts_with((string) $request->url(), 'https://example.test/api/v2/sign/pdf')
                && isset($body['file']) && is_array($body['file']) && is_string($body['file'][0] ?? '')
                && isset($body['signatureProperties']) && is_array($body['signatureProperties'])
                && $hasAuth && $hasIpHeader;
        });
    }

    public function test_sign_pdf_with_totp_includes_totp_and_not_passphrase(): void
    {
        Http::fake([
            '*/api/v2/sign/pdf' => Http::response(['file' => [$this->makeBase64Pdf()], 'message' => 'success'], 200),
        ]);

        $client = new EsignClient;
        $payload = [
            'email' => 'user@test.dev',
            'totp' => '123456',
            'signatureProperties' => [['tampilan' => 'VIS']],
            'file' => [$this->makeBase64Pdf()],
        ];
        $client->signPdf($payload);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return isset($body['totp']) && ! isset($body['passphrase']);
        });
    }

    public function test_check_user_status_calls_endpoint(): void
    {
        Http::fake([
            '*/api/v2/user/check/status' => Http::response(['status' => 'ISSUE'], 200),
        ]);
        $client = new EsignClient;
        $client->checkUserStatus(['nik' => '123']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/v2/user/check/status'));
    }

    public function test_verify_pdf_and_seal_endpoints(): void
    {
        Http::fake([
            '*/api/v2/verify/pdf' => Http::response(['conclusion' => 'VALID_SIGNATURE'], 200),
            '*/api/v2/seal/get/activation' => Http::response(['success' => true], 200),
            '*/api/v2/seal/get/totp' => Http::response(['success' => true, 'totp' => '654321'], 200),
            '*/api/v2/seal/pdf' => Http::response(['file' => [$this->makeBase64Pdf()]], 200),
        ]);

        $client = new EsignClient;
        $client->verifyPdf(['file' => [$this->makeBase64Pdf()]]);
        $client->getSealActivation(['idSubscriber' => 'ABC']);
        $client->getSealTotp(['idSubscriber' => 'ABC']);
        $client->sealPdf(['file' => [$this->makeBase64Pdf()], 'totp' => '654321', 'idSubscriber' => 'ABC']);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/v2/seal/pdf'));
    }
}
