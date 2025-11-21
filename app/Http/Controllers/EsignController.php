<?php

namespace App\Http\Controllers;

use App\Services\EsignClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EsignController extends Controller
{
    public function showForm(): Response
    {
        return Inertia::render('Esign/Sign');
    }

    public function submitSign(Request $request, EsignClient $esign)
    {
        $validated = $request->validate([
            'file_base64' => ['required', 'string'],
            'files_base64' => ['nullable', 'array'],
            'files_base64.*' => ['string'],
            'signer_id' => ['required_without:signer_email'],
            'signer_email' => ['required_without:signer_id', 'email'],
            'method' => ['required', 'in:passphrase,totp'],
            'passphrase' => ['required_if:method,passphrase'],
            'totp' => ['required_if:method,totp'],
            'tampilan' => ['nullable', 'in:VIS,INV'],
            'imageBase64' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'originX' => ['nullable', 'integer'],
            'originY' => ['nullable', 'integer'],
            'width' => ['nullable', 'integer'],
            'height' => ['nullable', 'integer'],
            'location' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        $signatureProperties = [
            [
                'tampilan' => $validated['tampilan'] ?? 'VIS',
                'imageBase64' => $validated['imageBase64'] ?? null,
                'page' => $validated['page'] ?? 1,
                'originX' => $validated['originX'] ?? 0,
                'originY' => $validated['originY'] ?? 0,
                'width' => $validated['width'] ?? 200,
                'height' => $validated['height'] ?? 100,
                'location' => $validated['location'] ?? null,
                'reason' => $validated['reason'] ?? null,
            ],
        ];

        $files = $validated['files_base64'] ?? [$validated['file_base64']];
        $payload = [
            'nik' => $validated['signer_id'] ?? null,
            'email' => $validated['signer_email'] ?? null,
            'passphrase' => $validated['method'] === 'passphrase' ? ($validated['passphrase'] ?? null) : null,
            'totp' => $validated['method'] === 'totp' ? ($validated['totp'] ?? null) : null,
            'signatureProperties' => $signatureProperties,
            'file' => $files,
        ];

        $rawBase64 = $validated['file_base64'];
        if (base64_decode($rawBase64, true) === false) {
            return back()->with('error', 'File tidak valid (Base64 rusak).');
        }

        $statusPayload = [
            'nik' => $validated['signer_id'] ?? null,
            'email' => $validated['signer_email'] ?? null,
        ];

        $statusResp = $esign->checkUserStatus($statusPayload);
        if ($statusResp->ok()) {
            $userStatus = data_get($statusResp->json(), 'status');
            if ($userStatus !== 'ISSUE') {
                return back()->with('error', 'Status sertifikat tidak memenuhi syarat: '.(string) $userStatus);
            }
        }

        $correlationId = (string) Str::uuid();
        $resp = $esign->signPdf($payload);

        if ($resp->failed()) {
            Log::warning('esign.sign.failed', [
                'correlation_id' => $correlationId,
                'user_id' => optional($request->user())->id,
                'endpoint' => '/api/v2/sign/pdf',
                'status' => $resp->status(),
                'message' => $resp->body(),
            ]);

            return back()->with('error', 'Gagal menandatangani dokumen: '.$resp->body());
        }

        $signedFiles = (array) data_get($resp->json(), 'file', []);
        $savedPaths = [];
        foreach ($signedFiles as $signedBase64) {
            $binary = base64_decode((string) $signedBase64, true);
            $filename = 'signed-'.(optional($request->user())->id ?? 'guest').'-'.now()->format('YmdHis').'-'.Str::random(6).'.pdf';
            $path = 'signed/'.$filename;
            Storage::put($path, $binary);
            $savedPaths[] = $path;
        }

        Log::info('esign.sign.success', [
            'correlation_id' => $correlationId,
            'user_id' => optional($request->user())->id,
            'endpoint' => '/api/v2/sign/pdf',
            'status' => $resp->status(),
            'file' => $savedPaths[0] ?? null,
        ]);

        return back()->with('success', 'Dokumen berhasil ditandatangani.')->with('signed_path', $savedPaths[0] ?? null)->with('signed_paths', $savedPaths);
    }

    public function requestTotp(Request $request, EsignClient $esign)
    {
        $validated = $request->validate([
            'signer_id' => ['required_without:signer_email'],
            'signer_email' => ['required_without:signer_id', 'email'],
        ]);

        $payload = [
            'nik' => $validated['signer_id'] ?? null,
            'email' => $validated['signer_email'] ?? null,
        ];

        $correlationId = (string) Str::uuid();
        $resp = $esign->getTotp($payload);

        if ($resp->failed()) {
            Log::warning('esign.totp.failed', [
                'correlation_id' => $correlationId,
                'user_id' => optional($request->user())->id,
                'endpoint' => '/api/v2/sign/get/totp',
                'status' => $resp->status(),
                'message' => $resp->body(),
            ]);

            return back()->with('error', 'Gagal meminta OTP: '.$resp->body());
        }

        Log::info('esign.totp.success', [
            'correlation_id' => $correlationId,
            'user_id' => optional($request->user())->id,
            'endpoint' => '/api/v2/sign/get/totp',
            'status' => $resp->status(),
        ]);

        return back()->with('success', 'OTP berhasil dikirim.');
    }

    public function verifyPdf(Request $request, EsignClient $esign)
    {
        $validated = $request->validate([
            'file_base64' => ['required', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        $payload = [
            'file' => [$validated['file_base64']],
            'password' => $validated['password'] ?? null,
        ];

        $correlationId = (string) Str::uuid();
        $resp = $esign->verifyPdf($payload);

        if ($resp->failed()) {
            Log::warning('esign.verify.failed', [
                'correlation_id' => $correlationId,
                'user_id' => optional($request->user())->id,
                'endpoint' => '/api/v2/verify/pdf',
                'status' => $resp->status(),
                'message' => $resp->body(),
            ]);

            return back()->with('error', 'Gagal verifikasi dokumen: '.$resp->body());
        }

        Log::info('esign.verify.success', [
            'correlation_id' => $correlationId,
            'user_id' => optional($request->user())->id,
            'endpoint' => '/api/v2/verify/pdf',
            'status' => $resp->status(),
        ]);

        return back()->with('success', 'Dokumen valid.');
    }

    public function downloadSigned(string $filename)
    {
        $path = 'signed/'.$filename;
        if (! Storage::exists($path)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($path) {
            echo Storage::get($path);
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}
