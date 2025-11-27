<?php

namespace Tests\Feature;

use App\Models\NotaDinas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_lampiran_uses_private_storage_and_download_route(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'skpd']);
        $this->actingAs($user);

        $asisten = User::factory()->create(['role' => 'asisten']);
        $skpd = \App\Models\Skpd::create(['nama_skpd' => 'SKPD Test', 'status' => true, 'asisten_id' => $asisten->id]);

        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-100',
            'perihal' => 'Uji',
            'anggaran' => 1000,
            'tanggal_pengajuan' => now(),
            'status' => 'draft',
            'tahap_saat_ini' => 'skpd',
        ]);

        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
        $res = $this->post(route('nota.pengiriman.store', $nota->id), [
            'lampiran' => [$file],
        ]);

        $res->assertRedirect();

        $lampiran = $nota->lampirans()->first();
        $this->assertNotNull($lampiran);
        $this->assertStringStartsWith('documents/'.$lampiran->id.'/original/', $lampiran->path);
        Storage::disk('local')->assertExists($lampiran->path);

        $res2 = $this->get(route('nota.lampiran.view', $lampiran->id));
        $res2->assertStatus(200);
    }
}
