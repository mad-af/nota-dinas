<?php

namespace Tests\Feature\Esign;

use App\Models\NotaDinas;
use App\Models\NotaLampiran;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignIntegrationTest extends TestCase
{
    protected function makeBase64Pdf(): string
    {
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

        return base64_encode($pdf);
    }

    public function test_sign_lampiran_calls_esign_and_marks_signed_on_success(): void
    {
        $user = User::factory()->create(['nik' => '1234567890123456']);
        $this->actingAs($user);

        Storage::fake('public');
        $skpd = Skpd::create(['nama_skpd' => 'SKPD Test', 'status' => true]);
        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-100',
            'perihal' => 'Test',
            'tanggal_pengajuan' => now(),
            'status' => 'proses',
            'tahap_saat_ini' => 'skpd',
        ]);
        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'lampiran.pdf',
            'path' => 'lampiran_nota/lampiran.pdf',
        ]);
        Storage::disk('public')->put($lampiran->path, base64_decode($this->makeBase64Pdf()));

        Http::fake([
            '*/api/v2/user/check/status' => Http::response(['status' => 'ISSUE'], 200),
            '*/api/v2/sign/pdf' => Http::response(['file' => [$this->makeBase64Pdf()]], 200),
        ]);

        $resp = $this->post(route('nota.lampiran.sign', $lampiran->id), [
            'method' => 'passphrase',
            'passphrase' => 'secret',
        ]);

        $resp->assertRedirect(route('nota.lampiran.view', $lampiran->id));
        $resp->assertSessionHas('success');
        $lampiran = $lampiran->fresh();
        $this->assertContains((string) $user->id, $lampiran->signature_user_ids ?? []);
    }

    public function test_sign_lampiran_handles_esign_failure_and_does_not_mark_signed(): void
    {
        $user = User::factory()->create(['nik' => '1234567890123456']);
        $this->actingAs($user);

        Storage::fake('public');
        $skpd = Skpd::create(['nama_skpd' => 'SKPD Test 2', 'status' => true]);
        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-101',
            'perihal' => 'Test',
            'tanggal_pengajuan' => now(),
            'status' => 'proses',
            'tahap_saat_ini' => 'skpd',
        ]);
        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'lampiran2.pdf',
            'path' => 'lampiran_nota/lampiran2.pdf',
        ]);
        Storage::disk('public')->put($lampiran->path, base64_decode($this->makeBase64Pdf()));

        Http::fake([
            '*/api/v2/user/check/status' => Http::response(['status' => 'ISSUE'], 200),
            '*/api/v2/sign/pdf' => Http::response(['error' => 'Invalid passphrase'], 400),
        ]);

        $resp = $this->post(route('nota.lampiran.sign', $lampiran->id), [
            'method' => 'passphrase',
            'passphrase' => 'wrong',
        ]);

        $resp->assertRedirect(route('nota.lampiran.view', $lampiran->id));
        $resp->assertSessionHas('error');
        $lampiran = $lampiran->fresh();
        $this->assertNotContains((string) $user->id, $lampiran->signature_user_ids ?? []);
    }
}
