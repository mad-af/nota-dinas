<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SignatureDocumentService
{
    protected string $disk = 'local';

    protected string $version = 'v1';

    public function storeOriginal(int $documentId, UploadedFile $file): array
    {
        $this->assertPdf($file);
        $uuid = (string) Str::uuid();
        $ts = now()->format('YmdHis');
        $rel = "documents/{$documentId}/original/{$ts}-{$uuid}.pdf";
        $bytes = file_get_contents($file->getRealPath());
        $hashBefore = hash('sha256', $bytes);
        Storage::disk($this->disk)->put($rel, $bytes);
        $readBack = Storage::disk($this->disk)->get($rel);
        $hashAfter = hash('sha256', $readBack);
        if ($hashBefore !== $hashAfter) {
            Storage::disk($this->disk)->delete($rel);
            throw new \RuntimeException('Integritas file gagal diverifikasi.');
        }
        Log::info('doc.original.uploaded', ['doc_id' => $documentId, 'path' => $rel]);

        return ['path' => $rel, 'hash' => $hashAfter, 'uuid' => $uuid, 'timestamp' => $ts];
    }

    public function storeSigned(int $documentId, UploadedFile $file, array $signerMeta = []): array
    {
        $this->assertPdf($file);
        $uuid = (string) Str::uuid();
        $ts = now()->format('YmdHis');
        $version = $this->version;
        $rel = "documents/{$documentId}/signed/{$version}/{$ts}-{$uuid}.pdf";
        $bytes = file_get_contents($file->getRealPath());
        $hashBefore = hash('sha256', $bytes);
        Storage::disk($this->disk)->put($rel, $bytes);
        $readBack = Storage::disk($this->disk)->get($rel);
        $hashAfter = hash('sha256', $readBack);
        if ($hashBefore !== $hashAfter) {
            Storage::disk($this->disk)->delete($rel);
            throw new \RuntimeException('Integritas file gagal diverifikasi.');
        }
        $manifestPath = $this->manifestPath($documentId, $version);
        $manifest = [
            'document_id' => (string) $documentId,
            'version' => $version,
            'timestamp' => $ts,
            'signer' => array_filter([
                'user_id' => $signerMeta['user_id'] ?? null,
                'name' => $signerMeta['name'] ?? null,
                'nik' => $signerMeta['nik'] ?? null,
                'method' => $signerMeta['method'] ?? null,
            ]),
            'doc_hash' => $hashAfter,
            'signature_meta' => $signerMeta['signature_meta'] ?? [],
            'file' => [
                'path' => $rel,
                'uuid' => $uuid,
            ],
        ];
        Storage::disk($this->disk)->put($manifestPath, json_encode($manifest));
        Log::info('doc.signed.uploaded', ['doc_id' => $documentId, 'path' => $rel, 'manifest' => $manifestPath]);

        return ['path' => $rel, 'hash' => $hashAfter, 'uuid' => $uuid, 'timestamp' => $ts, 'manifest' => $manifestPath];
    }

    public function storeSignedBase64(int $documentId, string $fileBase64, array $signerMeta = []): array
    {
        $uuid = (string) Str::uuid();
        $ts = now()->format('YmdHis');
        $version = $this->version;
        $rel = "documents/{$documentId}/signed/{$version}/{$ts}-{$uuid}.pdf";

        $normalized = $this->normalizeBase64($fileBase64);
        $bytes = base64_decode($normalized, true);
        if ($bytes === false || strncmp($bytes, '%PDF-', 5) !== 0) {
            throw new \InvalidArgumentException('Format file harus PDF (base64).');
        }

        $hashBefore = hash('sha256', $bytes);
        Storage::disk($this->disk)->put($rel, $bytes);
        $readBack = Storage::disk($this->disk)->get($rel);
        $hashAfter = hash('sha256', $readBack);
        if ($hashBefore !== $hashAfter) {
            Storage::disk($this->disk)->delete($rel);
            throw new \RuntimeException('Integritas file gagal diverifikasi.');
        }

        $manifestPath = $this->manifestPath($documentId, $version);
        $manifest = [
            'document_id' => (string) $documentId,
            'version' => $version,
            'timestamp' => $ts,
            'signer' => array_filter([
                'user_id' => $signerMeta['user_id'] ?? null,
                'name' => $signerMeta['name'] ?? null,
                'nik' => $signerMeta['nik'] ?? null,
                'method' => $signerMeta['method'] ?? null,
            ]),
            'doc_hash' => $hashAfter,
            'signature_meta' => $signerMeta['signature_meta'] ?? [],
            'file' => [
                'path' => $rel,
                'uuid' => $uuid,
            ],
        ];
        Storage::disk($this->disk)->put($manifestPath, json_encode($manifest));
        Log::info('doc.signed.uploaded.base64', ['doc_id' => $documentId, 'path' => $rel, 'manifest' => $manifestPath]);

        return ['path' => $rel, 'hash' => $hashAfter, 'uuid' => $uuid, 'timestamp' => $ts, 'manifest' => $manifestPath];
    }

    protected function normalizeBase64(string $b64): string
    {
        if (str_contains($b64, 'base64,')) {
            $parts = explode('base64,', $b64, 2);

            return $parts[1] ?? $b64;
        }

        return $b64;
    }

    public function latestOriginal(int $documentId): ?string
    {
        $dir = "documents/{$documentId}/original";
        $files = collect(Storage::disk($this->disk)->files($dir))->sort()->values();

        return $files->last();
    }

    public function latestSigned(int $documentId, ?string $version = null): ?string
    {
        $version = $version ?: $this->version;
        $dir = "documents/{$documentId}/signed/{$version}";
        $files = collect(Storage::disk($this->disk)->files($dir))->sort()->values();

        return $files->last();
    }

    public function manifestPath(int $documentId, ?string $version = null): string
    {
        $version = $version ?: $this->version;

        return "signatures/{$documentId}/{$version}.json";
    }

    public function getManifest(int $documentId, ?string $version = null): ?array
    {
        $path = $this->manifestPath($documentId, $version);
        if (! Storage::disk($this->disk)->exists($path)) {
            return null;
        }
        $raw = Storage::disk($this->disk)->get($path);
        $json = json_decode($raw, true);

        return is_array($json) ? $json : null;
    }

    public function signedVersions(int $documentId): array
    {
        $base = "documents/{$documentId}/signed";
        $dirs = collect(Storage::disk($this->disk)->directories($base))->map(function ($d) {
            return basename($d);
        })->values()->all();

        return $dirs;
    }

    protected function assertPdf(UploadedFile $file): void
    {
        $mime = $file->getClientMimeType();
        $name = $file->getClientOriginalName();
        $ok = str_ends_with(strtolower((string) $name), '.pdf') || $mime === 'application/pdf';
        if (! $ok) {
            throw new \InvalidArgumentException('Format file harus PDF.');
        }
    }
}
