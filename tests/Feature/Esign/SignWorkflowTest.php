<?php
// File: tests/Feature/Esign/SignWorkflowTest.php

namespace Tests\Feature\Esign;

use App\Models\User;
use App\Models\Skpd;
use App\Models\NotaDinas;
use App\Models\NotaLampiran;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\_support\CreatesBase64Pdf;

/**
 * Spec: file:///mnt/data/pdf-petunjuk-teknis-api-esign-client-service-v221-sign-2_compress.pdf
 */
class SignWorkflowTest extends TestCase
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

    public function test_successful_sign_with_passphrase_saves_pdf_and_logs_metadata(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Storage::fake();
        Log::spy();
        Http::fake([
            '*/api/v2/user/check/status' => Http::response(['status' => 'ISSUE'], 200),
            '*/api/v2/sign/pdf' => Http::response(['file' => [$this->makeBase64Pdf()], 'message' => 'success'], 200),
        ]);

        $resp = $this->post(route('esign.sign.submit'), [
            'file_base64' => $this->makeBase64Pdf(),
            'signer_id' => '1234567890123456',
            'method' => 'passphrase',
            'passphrase' => 'super-secret',
        ]);

        $resp->assertSessionHas('success');
        $path = session('signed_path');
        $this->assertTrue(Storage::exists($path));
        $this->assertStringStartsWith('%PDF-', base64_decode($this->makeBase64Pdf()));

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            return $message === 'esign.sign.success'
                && isset($context['correlation_id'], $context['user_id'], $context['endpoint'], $context['status'], $context['file'])
                && !isset($context['passphrase']) && !isset($context['totp']);
        });
    }

    public function test_successful_sign_with_otp_flow(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Storage::fake();
        Http::fake([
            '*/api/v2/sign/get/totp' => Http::response(['success' => true, 'message' => 'TOTP generated', 'totp' => '123456'], 200),
            '*/api/v2/user/check/status' => Http::response(['status' => 'ISSUE'], 200),
            '*/api/v2/sign/pdf' => Http::response(['file' => [$this->makeBase64Pdf()]], 200),
        ]);

        $this->post(route('esign.totp.request'), [
            'signer_id' => '1234567890123456',
        ])->assertSessionHas('success');

        $resp = $this->post(route('esign.sign.submit'), [
            'file_base64' => $this->makeBase64Pdf(),
            'signer_id' => '1234567890123456',
            'method' => 'totp',
            'totp' => '123456',
        ]);
        $resp->assertSessionHas('success');
        $this->assertTrue(Storage::exists(session('signed_path')));
    }

    public function test_user_status_not_issue_blocks_sign_and_no_sign_call(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Storage::fake();
        Http::fake([
            '*/api/v2/user/check/status' => Http::response(['status' => 'EXPIRED'], 200),
            '*/api/v2/sign/pdf' => Http::response(['file' => [$this->makeBase64Pdf()]], 200),
        ]);

        $resp = $this->post(route('esign.sign.submit'), [
            'file_base64' => $this->makeBase64Pdf(),
            'signer_id' => '1234567890123456',
            'method' => 'passphrase',
            'passphrase' => 'secret',
        ]);

        $resp->assertSessionHas('error');
        Http::assertNotSent(fn($r) => str_ends_with($r->url(), '/api/v2/sign/pdf'));
    }

    public function test_malformed_base64_is_rejected_and_no_api_call(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Http::fake();

        $resp = $this->post(route('esign.sign.submit'), [
            'file_base64' => 'not_base64!!',
            'signer_id' => '1234567890123456',
            'method' => 'passphrase',
            'passphrase' => 'secret',
        ]);

        $resp->assertSessionHas('error');
        Http::assertNothingSent();
    }

    public function test_bulk_signing_saves_all_files_with_unique_names(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Storage::fake();
        Http::fake([
            '*/api/v2/user/check/status' => Http::response(['status' => 'ISSUE'], 200),
            '*/api/v2/sign/pdf' => Http::response(['file' => [$this->makeBase64Pdf(), $this->makeBase64Pdf()]], 200),
        ]);

        $resp = $this->post(route('esign.sign.submit'), [
            'file_base64' => $this->makeBase64Pdf(),
            'files_base64' => [$this->makeBase64Pdf(), $this->makeBase64Pdf()],
            'signer_id' => '1234567890123456',
            'method' => 'passphrase',
            'passphrase' => 'secret',
        ]);

        $resp->assertSessionHas('success');
        $paths = session('signed_paths');
        $this->assertIsArray($paths);
        $this->assertCount(2, $paths);
        $this->assertTrue(Storage::exists($paths[0]));
        $this->assertTrue(Storage::exists($paths[1]));
        $this->assertNotEquals($paths[0], $paths[1]);
    }

    public function test_submit_sign_marks_lampiran_when_lampiran_id_provided(): void
    {
        $user = User::factory()->create(['nik' => '1234567890123456']);
        $this->actingAs($user);

        Storage::fake();
        Http::fake([
            '*/api/v2/user/check/status' => Http::response(['status' => 'ISSUE'], 200),
            '*/api/v2/sign/pdf' => Http::response(['file' => [$this->makeBase64Pdf()]], 200),
        ]);

        $skpd = Skpd::create(['nama_skpd' => 'SKPD T', 'status' => true]);
        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-777',
            'perihal' => 'Test Lampiran',
            'tanggal_pengajuan' => now(),
            'status' => 'proses',
            'tahap_saat_ini' => 'skpd',
        ]);
        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'lampiran777.pdf',
            'path' => 'lampiran_nota/lampiran777.pdf',
        ]);

        $resp = $this->post(route('esign.sign.submit'), [
            'file_base64' => $this->makeBase64Pdf(),
            'signer_id' => '1234567890123456',
            'method' => 'passphrase',
            'passphrase' => 'secret',
            'lampiran_id' => $lampiran->id,
        ]);

        $resp->assertSessionHas('success');
        $lampiran = $lampiran->fresh();
        $this->assertContains((string) $user->id, $lampiran->signature_user_ids ?? []);
    }
}