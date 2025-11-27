<?php

namespace App\Http\Controllers;

use App\Models\NotaDinas;
use App\Models\NotaLampiran;
use App\Services\SignatureDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SignatureDocumentController extends Controller
{
    protected function authorizeLampiranOrAbort(NotaLampiran $lampiran): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Akses tidak diizinkan');
        }

        if ($user->role === 'admin') {
            return;
        }

        $nota = NotaDinas::find($lampiran->nota_dinas_id);
        $latestPengiriman = $nota ? $nota->pengirimans()->latest('created_at')->first() : null;

        $allowed = false;

        if ($user->role === 'skpd' && $nota && $nota->skpd_id === $user->skpd_id) {
            $allowed = true;
        }

        if ($latestPengiriman) {
            if ($latestPengiriman->pengirim_id === $user->id) {
                $allowed = true;
            }
            if ($latestPengiriman->dikirim_ke === $user->role) {
                $allowed = true;
            }
        }

        $ids = array_map('strval', $lampiran->signature_user_ids ?? []);
        if (in_array((string) $user->id, $ids, true)) {
            $allowed = true;
        }

        if (! $allowed) {
            abort(403, 'Anda tidak memiliki akses ke lampiran ini.');
        }
    }

    public function uploadOriginal(Request $request, NotaLampiran $lampiran)
    {
        $this->authorizeLampiranOrAbort($lampiran);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf']]);
        $svc = app(SignatureDocumentService::class);
        try {
            $res = $svc->storeOriginal($lampiran->id, $request->file('file'));
            Log::info('doc.original.saved', ['doc_id' => $lampiran->id, 'path' => $res['path']]);

            return response()->json(['success' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            Log::error('doc.original.save_failed', ['doc_id' => $lampiran->id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function uploadSigned(Request $request, NotaLampiran $lampiran)
    {
        $this->authorizeLampiranOrAbort($lampiran);
        $request->validate([
            'file' => ['required_without:file_base64', 'file', 'mimes:pdf'],
            'file_base64' => ['required_without:file', 'string'],
        ]);
        $svc = app(SignatureDocumentService::class);
        try {
            $signer = [
                'user_id' => optional(Auth::user())->id,
                'name' => optional(Auth::user())->name,
                'nik' => optional(Auth::user())->nik,
                'method' => $request->string('method')->toString(),
                'signature_meta' => $request->input('signature_meta', []),
            ];
            if ($request->filled('file_base64')) {
                $res = $svc->storeSignedBase64($lampiran->id, (string) $request->input('file_base64'), $signer);
            } else {
                $res = $svc->storeSigned($lampiran->id, $request->file('file'), $signer);
            }

            return response()->json(['success' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            Log::error('doc.signed.save_failed', ['doc_id' => $lampiran->id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function download(Request $request, NotaLampiran $lampiran)
    {
        $this->authorizeLampiranOrAbort($lampiran);
        $type = $request->string('type')->toString();
        $version = $request->string('version')->toString() ?: 'v1';
        $svc = app(SignatureDocumentService::class);
        $path = null;
        if ($type === 'original') {
            $path = $svc->latestOriginal($lampiran->id);
            if (! $path) {
                $dbPath = (string) $lampiran->path;
                if ($dbPath) {
                    $disk = str_starts_with($dbPath, 'lampiran_nota') ? 'public' : 'local';
                    if (Storage::disk($disk)->exists($dbPath)) {
                        $filename = basename($dbPath);

                        return response()->streamDownload(function () use ($disk, $dbPath) {
                            echo Storage::disk($disk)->get($dbPath);
                        }, $filename, ['Content-Type' => 'application/pdf']);
                    }
                }
            }
        } elseif ($type === 'signed') {
            $path = $svc->latestSigned($lampiran->id, $version);
        }
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        $filename = basename($path);

        return response()->streamDownload(function () use ($path) {
            echo Storage::disk('local')->get($path);
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function manifest(Request $request, NotaLampiran $lampiran)
    {
        $this->authorizeLampiranOrAbort($lampiran);
        $version = $request->string('version')->toString() ?: 'v1';
        $svc = app(SignatureDocumentService::class);
        $json = $svc->getManifest($lampiran->id, $version);
        if (! $json) {
            return response()->json(['success' => false, 'message' => 'Manifest tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'data' => $json]);
    }

    public function versions(NotaLampiran $lampiran)
    {
        $this->authorizeLampiranOrAbort($lampiran);
        $svc = app(SignatureDocumentService::class);
        $versions = $svc->signedVersions($lampiran->id);

        return response()->json(['success' => true, 'data' => $versions]);
    }
}
