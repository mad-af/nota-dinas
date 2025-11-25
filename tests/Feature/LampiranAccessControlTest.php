<?php

namespace Tests\Feature;

use App\Models\NotaDinas;
use App\Models\NotaLampiran;
use App\Models\NotaPengiriman;
use App\Models\Skpd;
use App\Models\User;
use Tests\TestCase;

class LampiranAccessControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_guest_redirected_on_lampiran_routes()
    {
        $skpd = Skpd::create(['nama_skpd' => 'SKPD A', 'status' => true]);
        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-001',
            'perihal' => 'Pengujian',
            'anggaran' => 1000,
            'tanggal_pengajuan' => now(),
            'status' => 'draft',
            'tahap_saat_ini' => 'skpd',
        ]);
        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'lampiran.pdf',
            'path' => 'lampiran_nota/lampiran.pdf',
        ]);

        $this->get(route('nota.lampiran.view', $lampiran->id))->assertStatus(302);
        $this->get(route('nota.lampiran.sign.page', $lampiran->id))->assertStatus(302);
    }

    public function test_user_without_access_gets_403()
    {
        $skpdA = Skpd::create(['nama_skpd' => 'SKPD A', 'status' => true]);
        $skpdB = Skpd::create(['nama_skpd' => 'SKPD B', 'status' => true]);
        $nota = NotaDinas::create([
            'skpd_id' => $skpdA->id,
            'nomor_nota' => 'N-002',
            'perihal' => 'Pengujian',
            'anggaran' => 2000,
            'tanggal_pengajuan' => now(),
            'status' => 'draft',
            'tahap_saat_ini' => 'skpd',
        ]);
        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'lampiran2.pdf',
            'path' => 'lampiran_nota/lampiran2.pdf',
        ]);

        $user = User::factory()->create(['role' => 'skpd', 'skpd_id' => $skpdB->id]);
        $this->actingAs($user);

        $this->get(route('nota.lampiran.view', $lampiran->id))->assertStatus(403);
        $this->get(route('nota.lampiran.sign.page', $lampiran->id))->assertStatus(403);
    }

    public function test_skpd_same_can_access()
    {
        $skpd = Skpd::create(['nama_skpd' => 'SKPD X', 'status' => true]);
        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-003',
            'perihal' => 'Pengujian',
            'anggaran' => 3000,
            'tanggal_pengajuan' => now(),
            'status' => 'draft',
            'tahap_saat_ini' => 'skpd',
        ]);
        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'lampiran3.pdf',
            'path' => 'lampiran_nota/lampiran3.pdf',
        ]);

        $user = User::factory()->create(['role' => 'skpd', 'skpd_id' => $skpd->id]);
        $this->actingAs($user);

        $this->get(route('nota.lampiran.view', $lampiran->id))->assertStatus(200);
        $this->get(route('nota.lampiran.sign.page', $lampiran->id))->assertStatus(200);
    }

    public function test_sender_can_access()
    {
        $skpd = Skpd::create(['nama_skpd' => 'SKPD Y', 'status' => true]);
        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-004',
            'perihal' => 'Pengujian',
            'anggaran' => 4000,
            'tanggal_pengajuan' => now(),
            'status' => 'proses',
            'tahap_saat_ini' => 'asisten',
        ]);
        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'lampiran4.pdf',
            'path' => 'lampiran_nota/lampiran4.pdf',
        ]);

        $sender = User::factory()->create(['role' => 'asisten']);
        $this->actingAs($sender);

        $pengiriman = NotaPengiriman::create([
            'nota_dinas_id' => $nota->id,
            'dikirim_dari' => 'skpd',
            'dikirim_ke' => 'asisten',
            'pengirim_id' => $sender->id,
        ]);
        $pengiriman->lampirans()->attach([$lampiran->id]);

        $this->get(route('nota.lampiran.view', $lampiran->id))->assertStatus(200);
        $this->get(route('nota.lampiran.sign.page', $lampiran->id))->assertStatus(200);
    }

    public function test_recipient_role_can_access()
    {
        $skpd = Skpd::create(['nama_skpd' => 'SKPD Z', 'status' => true]);
        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-005',
            'perihal' => 'Pengujian',
            'anggaran' => 5000,
            'tanggal_pengajuan' => now(),
            'status' => 'proses',
            'tahap_saat_ini' => 'sekda',
        ]);
        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'lampiran5.pdf',
            'path' => 'lampiran_nota/lampiran5.pdf',
        ]);

        $recipient = User::factory()->create(['role' => 'sekda']);
        $sender = User::factory()->create(['role' => 'asisten']);
        $this->actingAs($recipient);

        $pengiriman = NotaPengiriman::create([
            'nota_dinas_id' => $nota->id,
            'dikirim_dari' => 'asisten',
            'dikirim_ke' => 'sekda',
            'pengirim_id' => $sender->id,
        ]);
        $pengiriman->lampirans()->attach([$lampiran->id]);

        $this->get(route('nota.lampiran.view', $lampiran->id))->assertStatus(200);
        $this->get(route('nota.lampiran.sign.page', $lampiran->id))->assertStatus(200);
    }

    public function test_signer_can_access_and_sign()
    {
        $skpd = Skpd::create(['nama_skpd' => 'SKPD T', 'status' => true]);
        $nota = NotaDinas::create([
            'skpd_id' => $skpd->id,
            'nomor_nota' => 'N-006',
            'perihal' => 'Pengujian',
            'anggaran' => 6000,
            'tanggal_pengajuan' => now(),
            'status' => 'proses',
            'tahap_saat_ini' => 'bupati',
        ]);
        $lampiran = NotaLampiran::create([
            'nota_dinas_id' => $nota->id,
            'nama_file' => 'lampiran6.pdf',
            'path' => 'lampiran_nota/lampiran6.pdf',
            'signature_user_ids' => [],
        ]);

        $signer = User::factory()->create(['role' => 'skpd', 'skpd_id' => $skpd->id]);
        $this->actingAs($signer);

        $this->get(route('nota.lampiran.sign.page', $lampiran->id))->assertStatus(200);

        $this->post(route('nota.lampiran.sign', $lampiran->id))
            ->assertRedirect(route('nota.lampiran.view', $lampiran->id));

        $lampiran->refresh();
        $this->assertTrue(in_array((string) $signer->id, $lampiran->signature_user_ids ?? []));
    }
}