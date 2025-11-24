<?php

namespace Tests\Unit;

use App\Models\NotaDinas;
use App\Models\NotaLampiran;
use App\Models\Skpd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaLampiranSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_signature_user_id_adds_string_and_prevents_duplicates(): void
    {
        $skpd = Skpd::create(['nama_skpd' => 'SKPD Test']);
        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'ND-001',
            'perihal' => 'Pengujian',
            'tanggal_pengajuan' => now()->toDateString(),
        ]);

        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'doc.pdf',
            'path' => 'lampiran/doc.pdf',
        ]);

        $lampiran->addSignatureUserId(7);
        $lampiran->addSignatureUserId('7');
        $lampiran->addSignatureUserId(8);
        $lampiran->save();

        $this->assertIsArray($lampiran->signature_user_ids);
        $this->assertSame(['7', '8'], $lampiran->signature_user_ids);
    }
}
