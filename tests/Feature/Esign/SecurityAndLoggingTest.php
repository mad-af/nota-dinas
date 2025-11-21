<?php
// File: tests/Feature/Esign/SecurityAndLoggingTest.php

namespace Tests\Feature\Esign;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\_support\CreatesBase64Pdf;

/**
 * Spec: file:///mnt/data/pdf-petunjuk-teknis-api-esign-client-service-v221-sign-2_compress.pdf
 */
class SecurityAndLoggingTest extends TestCase
{
    use CreatesBase64Pdf;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.esign.url' => 'http://localhost',
            'services.esign.user' => 'user',
            'services.esign.pass' => 'pass',
        ]);
    }

    public function test_no_passphrase_or_otp_logged_on_success_or_failure(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Storage::fake();
        Http::fake([
            '*/api/v2/user/check/status' => Http::response(['status' => 'ISSUE'], 200),
            '*/api/v2/sign/pdf' => Http::response(['file' => [$this->makeBase64Pdf()], 'message' => 'success'], 200),
        ]);

        $mock = Log::spy();

        $this->post(route('esign.sign.submit'), [
            'file_base64' => $this->makeBase64Pdf(),
            'signer_id' => '1234567890123456',
            'method' => 'passphrase',
            'passphrase' => 'secret-value',
        ])->assertSessionHas('success');

        $mock->shouldHaveReceived('info')->withArgs(function ($message, $context) {
            $contextStr = json_encode($context);
            return !str_contains($contextStr, 'secret-value') && !str_contains($contextStr, 'totp');
        });
    }

    public function test_logging_on_failure_records_audit_fields_without_secrets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            '*/api/v2/user/check/status' => Http::response(['status' => 'ISSUE'], 200),
            '*/api/v2/sign/pdf' => Http::response(['error' => 'Invalid passphrase', 'code' => 400], 400),
        ]);

        $mock = Log::spy();

        $this->post(route('esign.sign.submit'), [
            'file_base64' => $this->makeBase64Pdf(),
            'signer_id' => '1234567890123456',
            'method' => 'passphrase',
            'passphrase' => 'secret-value',
        ])->assertSessionHas('error');

        $mock->shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            $contextStr = json_encode($context);
            return $message === 'esign.sign.failed'
                && isset($context['correlation_id'], $context['user_id'], $context['endpoint'], $context['status'], $context['message'])
                && !str_contains($contextStr, 'secret-value') && !str_contains($contextStr, 'totp');
        });
    }
}