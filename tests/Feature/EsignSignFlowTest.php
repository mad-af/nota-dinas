<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EsignSignFlowTest extends TestCase
{
    public function test_sign_flow_saves_signed_pdf_and_no_db_writes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Storage::fake();
        Http::fake([
            '*/api/v2/sign/pdf' => Http::response(['file' => [base64_encode('%PDF-1.4 fake')]], 200),
        ]);

        $beforeUserCount = User::count();

        $resp = $this->post(route('esign.sign.submit'), [
            'file_base64' => base64_encode('%PDF-1.4 fake'),
            'signer_id' => '1234567890123456',
            'method' => 'passphrase',
            'passphrase' => 'secret',
        ]);

        $resp->assertSessionHas('success');
        $path = session('signed_path');
        $this->assertNotEmpty($path);
        $this->assertTrue(Storage::exists($path));
        $this->assertSame($beforeUserCount, User::count());
    }

    public function test_verify_endpoint_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            '*/api/v2/verify/pdf' => Http::response(['status' => 'valid'], 200),
        ]);

        $resp = $this->post(route('esign.verify'), [
            'file_base64' => base64_encode('%PDF-1.4 fake'),
        ]);

        $resp->assertSessionHas('success');
    }
}