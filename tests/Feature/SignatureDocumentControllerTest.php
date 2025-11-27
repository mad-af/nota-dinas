<?php

namespace Tests\Feature;

use App\Models\NotaDinas;
use App\Models\NotaLampiran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBase64Pdf(): string
    {
        return base64_encode('%PDF-FAKE');
    }

    public function test_upload_signed_base64_and_manifest(): void
    {
        Storage::fake('local');
        $skpd = \App\Models\Skpd::create(['nama_skpd' => 'SKPD', 'status' => true]);
        $user = User::factory()->create(['role' => 'skpd', 'skpd_id' => $skpd->id]);
        $this->actingAs($user);

        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-101',
            'perihal' => 'Uji',
            'anggaran' => 1000,
            'tanggal_pengajuan' => now(),
            'status' => 'proses',
            'tahap_saat_ini' => 'skpd',
        ]);
        $lampiran = NotaLampiran::create(['nota_dinas_id' => $nota->id, 'nama_file' => 'l.pdf', 'path' => '']);

        $res = $this->post(route('documents.signed.upload', $lampiran->id), [
            'file_base64' => $this->makeBase64Pdf(),
            'method' => 'passphrase',
            'signature_meta' => ['k' => 'v'],
        ]);

        $res->assertStatus(200)->assertJson(['success' => true]);

        $manifest = 'signatures/'.$lampiran->id.'/v1.json';
        Storage::disk('local')->assertExists($manifest);

        $manifestJson = json_decode(Storage::disk('local')->get($manifest), true);
        $this->assertSame('v1', $manifestJson['version']);
        $this->assertSame((string) $lampiran->id, $manifestJson['document_id']);
        $this->assertArrayHasKey('doc_hash', $manifestJson);
    }
}
