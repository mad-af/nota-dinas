<?php

namespace App\Http\Controllers;

use App\Models\NotaLampiran;
use App\Services\SignatureDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PublicDocumentController extends Controller
{
    public function qr(Request $request, string $code)
    {
        try {
            $decoded = Crypt::decryptString($code);
        } catch (\Throwable $e) {
            abort(404);
        }
        $id = (int) $decoded;
        if ($id < 1) {
            abort(404);
        }
        $tokenPayload = json_encode(['id' => $id, 'exp' => now()->addHour()->timestamp]);
        $token = Crypt::encryptString($tokenPayload);
        $url = route('public.document.view', ['id' => $id, 'token' => $token]);

        return redirect($url, 302);
    }

    public function view(Request $request, int $id)
    {
        $code = (string) $request->query('token');
        $lampiran = NotaLampiran::findOrFail($id);
        $svc = app(SignatureDocumentService::class);
        $signed = $svc->latestSigned($lampiran->id) ?: $svc->latestSigned($lampiran->id, 'v1');
        $isSigned = ! empty($signed);
        $pdfUrl = route('public.document.stream', ['id' => $lampiran->id, 'token' => $code]);

        return Inertia::render('Public/DocumentView', [
            'doc' => ['id' => (string) $lampiran->id, 'name' => (string) ($lampiran->nama_file ?? 'Dokumen'), 'url' => $pdfUrl],
            'hasSigned' => $isSigned,
            'signers' => [],
            'currentUserId' => '',
        ]);
    }

    public function stream(Request $request, int $id)
    {
        $code = (string) $request->query('token');
        $lampiran = NotaLampiran::findOrFail($id);
        $svc = app(SignatureDocumentService::class);
        $path = $svc->latestSigned($lampiran->id) ?: $svc->latestOriginal($lampiran->id) ?: (string) $lampiran->path;
        if (! $path) {
            abort(404);
        }
        $filename = basename($path);

        return response()->streamDownload(function () use ($path) {
            echo Storage::disk('local')->get($path);
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}
