<?php

namespace Tests\Unit;

use App\Services\EsignClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EsignClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.esign.url' => 'https://example.test',
            'services.esign.user' => 'user',
            'services.esign.pass' => 'pass',
        ]);
    }

    public function test_sign_pdf_calls_correct_endpoint_with_auth(): void
    {
        $requests = [];
        Http::fake(function ($request) use (&$requests) {
            $requests[] = $request;
            return Http::response(['file' => ['ZmFrZQ==']], 200);
        });

        $client = new EsignClient();
        $client->signPdf(['file' => ['Zm9v']]);

        $this->assertNotEmpty($requests);
        $req = $requests[0];
        $this->assertStringEndsWith('/api/v2/sign/pdf', $req->url());
        $this->assertArrayHasKey('Authorization', $req->headers());
    }

    public function test_get_totp_calls_correct_endpoint_with_auth(): void
    {
        $requests = [];
        Http::fake(function ($request) use (&$requests) {
            $requests[] = $request;
            return Http::response(['status' => 'ok'], 200);
        });

        $client = new EsignClient();
        $client->getTotp(['email' => 'a@b.c']);

        $this->assertNotEmpty($requests);
        $req = $requests[0];
        $this->assertStringEndsWith('/api/v2/sign/get/totp', $req->url());
        $this->assertArrayHasKey('Authorization', $req->headers());
    }

    public function test_verify_pdf_calls_correct_endpoint_with_auth(): void
    {
        $requests = [];
        Http::fake(function ($request) use (&$requests) {
            $requests[] = $request;
            return Http::response(['status' => 'valid'], 200);
        });

        $client = new EsignClient();
        $client->verifyPdf(['file' => ['Zm9v']]);

        $this->assertNotEmpty($requests);
        $req = $requests[0];
        $this->assertStringEndsWith('/api/v2/verify/pdf', $req->url());
        $this->assertArrayHasKey('Authorization', $req->headers());
    }

    public function test_check_user_status_calls_correct_endpoint_with_auth(): void
    {
        $requests = [];
        Http::fake(function ($request) use (&$requests) {
            $requests[] = $request;
            return Http::response(['status' => 'ISSUE'], 200);
        });

        $client = new EsignClient();
        $client->checkUserStatus(['nik' => '123']);

        $this->assertNotEmpty($requests);
        $req = $requests[0];
        $this->assertStringEndsWith('/api/v2/user/check/status', $req->url());
        $this->assertArrayHasKey('Authorization', $req->headers());
    }

    public function test_seal_pdf_calls_correct_endpoint_with_auth(): void
    {
        $requests = [];
        Http::fake(function ($request) use (&$requests) {
            $requests[] = $request;
            return Http::response(['file' => ['ZmFrZQ==']], 200);
        });

        $client = new EsignClient();
        $client->sealPdf(['file' => ['Zm9v']]);

        $this->assertNotEmpty($requests);
        $req = $requests[0];
        $this->assertStringEndsWith('/api/v2/seal/pdf', $req->url());
        $this->assertArrayHasKey('Authorization', $req->headers());
    }
}