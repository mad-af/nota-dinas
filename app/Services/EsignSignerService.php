<?php

namespace App\Services;

use App\Models\NotaLampiran;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EsignSignerService
{
    public function __construct(private EsignClient $esign)
    {
    }

    public function signFileBase64(User $user, string $fileBase64, array $options): array
    {
        $raw = $fileBase64;
        if (base64_decode($raw, true) === false) {
            return ['success' => false, 'message' => 'File tidak valid (Base64 rusak).'];
        }

        $statusPayload = [];
        if ($user->nik) {
            $statusPayload['nik'] = $user->nik;
        }
        // if ($user->email) {
        //     $statusPayload['email'] = $user->email;
        // }

        $statusResp = $this->esign->checkUserStatus($statusPayload);
        if ($statusResp->ok()) {
            $userStatus = data_get($statusResp->json(), 'status');
            if ($userStatus !== 'ISSUE') {
                return ['success' => false, 'message' => 'Status sertifikat tidak memenuhi syarat: '.(string) $userStatus, 'coba' => $statusResp->json(), 'hehe'=>$statusPayload];
            }
        }

        $signatureProperties = [
            [
                'tampilan' => $options['tampilan'] ?? 'VIS',
                'imageBase64' => $options['imageBase64'] ?? null,
                'page' => $options['page'] ?? 1,
                'originX' => $options['originX'] ?? 0,
                'originY' => $options['originY'] ?? 0,
                'width' => $options['width'] ?? 200,
                'height' => $options['height'] ?? 100,
                'location' => $options['location'] ?? null,
                'reason' => $options['reason'] ?? null,
            ],
        ];

        $files = $options['files_base64'] ?? [$raw];
        $payload = [
            'nik' => $options['signer_id'] ?? ($user->nik ?? null),
            'email' => $options['signer_email'] ?? null,
            'passphrase' => ($options['method'] ?? null) === 'passphrase' ? ($options['passphrase'] ?? null) : null,
            'totp' => ($options['method'] ?? null) === 'totp' ? ($options['totp'] ?? null) : null,
            'signatureProperties' => $signatureProperties,
            'file' => $files,
        ];

        $correlationId = (string) Str::uuid();
        $resp = $this->esign->signPdf($payload);

        if ($resp->failed()) {
            Log::warning('esign.sign.failed', [
                'correlation_id' => $correlationId,
                'user_id' => $user->id ?? null,
                'endpoint' => '/api/v2/sign/pdf',
                'status' => $resp->status(),
                'message' => $resp->body(),
            ]);
            return ['success' => false, 'message' => 'Gagal menandatangani dokumen: '.$resp->body()];
        }

        $signedFiles = (array) data_get($resp->json(), 'file', []);
        $savedPaths = [];
        foreach ($signedFiles as $signedBase64) {
            $binary = base64_decode((string) $signedBase64, true);
            $filename = 'signed-'.($user->id ?? 'guest').'-'.now()->format('YmdHis').'-'.Str::random(6).'.pdf';
            $path = 'signed/'.$filename;
            Storage::put($path, $binary);
            $savedPaths[] = $path;
        }

        Log::info('esign.sign.success', [
            'correlation_id' => $correlationId,
            'user_id' => $user->id ?? null,
            'endpoint' => '/api/v2/sign/pdf',
            'status' => $resp->status(),
            'file' => $savedPaths[0] ?? null,
        ]);

        return ['success' => true, 'paths' => $savedPaths];
    }

    public function signLampiran(User $user, NotaLampiran $lampiran, array $options): array
    {
        try {
            $contents = Storage::disk('public')->get($lampiran->path);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gagal membaca file lampiran.'];
        }

        $base64 = base64_encode($contents);
        return $this->signFileBase64($user, $base64, $options);
    }
}