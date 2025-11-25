<?php

namespace App\Http\Controllers;

use App\Services\EsignClient;
use App\Services\EsignSignerService;
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
            'tampilan' => ['nullable', 'in:VISIBLE,INVISIBLE,VIS,INV'],
            'imageBase64' => ['nullable', 'string'],
            'signature_path' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'originX' => ['nullable', 'numeric'],
            'originY' => ['nullable', 'numeric'],
            'width' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'tag_koordinat' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'pdfPassword' => ['nullable', 'string'],
        ]);

        $service = app(EsignSignerService::class);
        $result = $service->signFileBase64(
            $request->user(),
            $validated['file_base64'],
            [
                'files_base64' => $validated['files_base64'] ?? null,
                'signer_id' => $validated['signer_id'] ?? null,
                'signer_email' => $validated['signer_email'] ?? null,
                'method' => $validated['method'] ?? null,
                'passphrase' => $validated['passphrase'] ?? null,
                'totp' => $validated['totp'] ?? null,
                'tampilan' => $validated['tampilan'] ?? null,
                'imageBase64' => $validated['imageBase64'] ?? null,
                'signature_path' => $validated['signature_path'] ?? null,
                'page' => $validated['page'] ?? null,
                'originX' => $validated['originX'] ?? null,
                'originY' => $validated['originY'] ?? null,
                'width' => $validated['width'] ?? null,
                'height' => $validated['height'] ?? null,
                'location' => $validated['location'] ?? null,
                'reason' => $validated['reason'] ?? null,
            ]
        );

        if (!($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Gagal menandatangani dokumen.');
        }

        return back()->with('success', 'Dokumen berhasil ditandatangani.')->with('signed_path', ($result['paths'][0] ?? null))->with('signed_paths', $result['paths'] ?? []);
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
