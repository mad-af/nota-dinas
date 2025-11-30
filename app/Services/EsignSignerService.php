<?php

namespace App\Services;

use App\Models\NotaLampiran;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EsignSignerService
{
    public function __construct(private EsignClient $esign) {}

    public function signFileBase64(User $user, string $fileBase64, array $options): array
    {
        $raw = $fileBase64;
        if (base64_decode($raw, true) === false) {
            return ['success' => false, 'message' => 'File tidak valid (Base64 rusak).'];
        }
        $binary = base64_decode($raw, true);
        if (! is_string($binary) || strncmp($binary, '%PDF-', 5) !== 0) {
            return ['success' => false, 'message' => 'File tidak valid (bukan PDF).'];
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
                return ['success' => false, 'message' => 'Status sertifikat tidak memenuhi syarat: '.(string) $userStatus];
            }
        }

        $tampilanInput = $options['tampilan'] ?? 'VISIBLE';
        $tampilan = $tampilanInput === 'VIS' ? 'VISIBLE' : ($tampilanInput === 'INV' ? 'INVISIBLE' : $tampilanInput);
        $imageBase64 = $options['imageBase64'] ?? null;
        if ($tampilan === 'VISIBLE' && ! $imageBase64 && ! empty($options['signature_path'])) {
            try {
                $imgBytes = Storage::disk('public')->get((string) $options['signature_path']);
                $imageBase64 = base64_encode($imgBytes);
            } catch (\Throwable $e) {
            }
        }
        $originX = isset($options['originX']) ? ($options['originX']) : 0;
        $originY = isset($options['originY']) ? ($options['originY']) : 0;
        $width = isset($options['width']) ? ($options['width']) : 200;
        $height = isset($options['height']) ? ($options['height']) : 100;
        $signatureProperties = [
            array_filter([
                'tampilan' => $tampilan,
                'imageBase64' => $imageBase64,
                'page' => $options['page'] ?? 1,
                'originX' => $originX,
                'originY' => $originY,
                'width' => $width,
                'height' => $height,
                'tag_koordinat' => $options['tag_koordinat'] ?? null,
                'location' => $options['location'] ?? null,
                'reason' => $options['reason'] ?? null,
                'pdfPassword' => $options['pdfPassword'] ?? null,
            ], function ($v) {
                return $v !== null;
            }),
        ];

        if ($tampilan === 'VISIBLE') {
            $byTag = ! empty($options['tag_koordinat']);
            $hasCoords = isset($options['page'], $options['originX'], $options['originY'], $options['width'], $options['height']);
            if (! ($byTag || $hasCoords)) {
                return ['success' => false, 'message' => 'Koordinat atau tag_koordinat wajib untuk tampilan VISIBLE.', 'coba' => [$byTag, $hasCoords, $options]];
            }
        }

        $files = $options['files_base64'] ?? [$raw];
        foreach ((array) $files as $f) {
            $b = base64_decode((string) $f, true);
            if ($b === false || strncmp((string) $b, '%PDF-', 5) !== 0) {
                return ['success' => false, 'message' => 'File array berisi data bukan PDF base64.'];
            }
        }
        $nik = $options['signer_id'] ?? ($user->nik ?? null);
        $email = $options['signer_email'] ?? null;
        if (! ($nik || $email)) {
            return ['success' => false, 'message' => 'Harus ada nik (16 digit) atau email.'];
        }
        if ($nik && ! preg_match('/^\d{16}$/', (string) $nik)) {
            return ['success' => false, 'message' => 'NIK harus 16 digit.'];
        }
        $method = $options['method'] ?? null;
        $passphrase = ($method === 'passphrase') ? ($options['passphrase'] ?? null) : null;
        $totp = ($method === 'totp') ? ($options['totp'] ?? null) : null;
        if ($method === 'passphrase' && ! $passphrase) {
            return ['success' => false, 'message' => 'Passphrase wajib untuk metode passphrase.'];
        }
        if ($method === 'totp' && ! $totp) {
            return ['success' => false, 'message' => 'TOTP wajib untuk metode totp.'];
        }
        if (! in_array($method, ['passphrase', 'totp'])) {
            return ['success' => false, 'message' => 'Metode tanda tangan tidak valid.'];
        }

        $payload = array_filter([
            'nik' => $nik,
            'email' => $email,
            'passphrase' => $passphrase,
            'totp' => $totp,
            'signatureProperties' => $signatureProperties,
            'file' => $files,
        ], function ($v) {
            return $v !== null;
        });

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

            return ['success' => false, 'message' => 'Gagal menandatangani dokumen: '.$resp->body(), 'curl' => $payload];
        }

        $signedFiles = (array) data_get($resp->json(), 'file', []);
        $savedPaths = [];
        $manifests = [];

        $documentId = (int) ($options['document_id'] ?? ($options['lampiran_id'] ?? 0));
        if ($documentId > 0) {
            $svc = app(SignatureDocumentService::class);
            $signerMeta = [
                'user_id' => $user->id ?? null,
                'name' => $user->name ?? null,
                'nik' => $user->nik ?? null,
                'method' => $method,
                'signature_meta' => array_filter([
                    'tampilan' => $tampilan,
                    'page' => $options['page'] ?? null,
                    'originX' => $originX,
                    'originY' => $originY,
                    'width' => $width,
                    'height' => $height,
                    'tag_koordinat' => $options['tag_koordinat'] ?? null,
                    'location' => $options['location'] ?? null,
                    'reason' => $options['reason'] ?? null,
                    'pdfPassword' => $options['pdfPassword'] ?? null,
                ], fn ($v) => $v !== null),
            ];

            foreach ($signedFiles as $signedBase64) {
                $res = $svc->storeSignedBase64($documentId, (string) $signedBase64, $signerMeta);
                $savedPaths[] = $res['path'];
                if (! empty($res['manifest'])) {
                    $manifests[] = $res['manifest'];
                }
            }
        } else {
            foreach ($signedFiles as $signedBase64) {
                $binary = base64_decode((string) $signedBase64, true);
                $filename = 'signed-'.($user->id ?? 'guest').'-'.now()->format('YmdHis').'-'.Str::random(6).'.pdf';
                $path = 'signed/'.$filename;
                Storage::disk('local')->put($path, $binary);
                $savedPaths[] = $path;
            }
        }

        Log::info('esign.sign.success', [
            'correlation_id' => $correlationId,
            'user_id' => $user->id ?? null,
            'endpoint' => '/api/v2/sign/pdf',
            'status' => $resp->status(),
            'file' => $savedPaths[0] ?? null,
        ]);

        return ['success' => true, 'paths' => $savedPaths, 'manifests' => $manifests];
    }

    public function signLampiran(User $user, NotaLampiran $lampiran, array $options): array
    {
        $svc = app(SignatureDocumentService::class);
        $path = $svc->latestOriginal($lampiran->id) ?: (string) $lampiran->path;
        $signedExists = false;
        foreach ($svc->signedVersions($lampiran->id) as $ver) {
            if ($svc->latestSigned($lampiran->id, $ver)) {
                $signedExists = true;
                break;
            }
        }
        try {
            if (! $signedExists) {
                $absolute = Storage::disk('local')->path($path);
                $code = $this->encodeBase62((int) $lampiran->id);
                $publicQrUrl = route('public.qr', ['code' => $code]);
                $contents = $svc->addElectronicSignatureFooter($absolute, $publicQrUrl);
            } else {
                $contents = Storage::disk('local')->get($path);
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gagal membaca/menyiapkan file lampiran.'];
        }

        $base64 = base64_encode($contents);
        if (! empty($options['signature_path'])) {
            try {
                $imgBytes = Storage::disk('public')->get((string) $options['signature_path']);
                $options['imageBase64'] = base64_encode($imgBytes);
            } catch (\Throwable $e) {
            }
        }

        $options['document_id'] = $lampiran->id;

        return $this->signFileBase64($user, $base64, $options);
    }

    private function encodeBase62(int $n): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        if ($n === 0) {
            return '0';
        }
        $s = '';
        while ($n > 0) {
            $r = $n % 62;
            $s = $alphabet[$r].$s;
            $n = intdiv($n, 62);
        }

        return $s;
    }
}
