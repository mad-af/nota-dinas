<?php

namespace App\Http\Controllers;

use App\Models\NotaDinas;
use App\Models\NotaLampiran;
use App\Models\NotaPengiriman;
use App\Models\NotaPersetujuan;
use App\Models\User;
use App\Services\EsignSignerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class NotaDinasController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $search = $request->input('search');

        $query = NotaDinas::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nomor_nota', 'like', "%$search%")
                    ->orWhere('perihal', 'like', "%$search%")
                    ->orWhere('tahap_saat_ini', 'like', "%$search%");
            });
        }

        switch ($user->role) {
            case 'skpd':
                $query->where('skpd_id', $user->skpd_id)
                    ->whereIn('status', ['draft', 'dikembalikan', 'proses']);
                break;
            case 'asisten':
                $query->whereHas('skpd', function ($q) use ($user) {
                    $q->where('asisten_id', $user->id);
                })->where('status', 'proses')
                    ->where('tahap_saat_ini', 'asisten');
                break;
            case 'sekda':
                $query->where('tahap_saat_ini', 'sekda')
                    ->where('status', 'proses');
                break;
            case 'bupati':
                $query->where('tahap_saat_ini', 'bupati')
                    ->where('status', 'proses');
                break;
            case 'admin':
                // Admin can see all data
                break;
            default:
                return abort(403, 'Akses tidak diizinkan');
        }

        $notas = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('NotaDinas/Index', [
            'notas' => $notas,
            'search' => $search,
        ]);
    }

    public function viewLampiran($lampiranId)
    {
        $lampiran = NotaLampiran::findOrFail($lampiranId);
        $this->authorizeLampiranOrAbort($lampiran);
        $ids = array_map('strval', $lampiran->signature_user_ids ?? []);
        $signers = User::whereIn('id', $ids)->select('id', 'name')->get()->map(function ($u) {
            return ['id' => (string) $u->id, 'name' => $u->name];
        })->values();
        $currentUserId = (string) (Auth::id());
        $hasSigned = in_array($currentUserId, $ids, true);
        $latestSignature = $lampiran->signatures()->orderBy('created_at', 'desc')->first();
        $useSigned = false;
        if ($latestSignature) {
            $path = (string) $latestSignature->path;
            if ($path && Storage::disk('local')->exists($path)) {
                $useSigned = true;
                Log::info('nota.lampiran.view.use_signed', ['lampiran_id' => $lampiran->id, 'path' => $path]);
            } else {
                Log::warning('nota.lampiran.view.missing_signed', ['lampiran_id' => $lampiran->id, 'path' => $path]);
            }
        }
        if (! $useSigned) {
            $origPath = (string) $lampiran->path;
            if ($origPath && Storage::disk('local')->exists($origPath)) {
                Log::info('nota.lampiran.view.use_original', ['lampiran_id' => $lampiran->id, 'path' => $origPath]);
            } else {
                Log::warning('nota.lampiran.view.missing_original', ['lampiran_id' => $lampiran->id, 'path' => $origPath]);
            }
        }
        $doc = [
            'id' => $lampiran->id,
            'name' => $lampiran->nama_file,
            'url' => $useSigned
                ? route('documents.download', ['lampiran' => $lampiran->id, 'type' => 'signed', 'version' => 'v1'])
                : route('documents.download', ['lampiran' => $lampiran->id, 'type' => 'original']),
            'nota_dinas_id' => $lampiran->nota_dinas_id,
        ];

        return Inertia::render('NotaDinas/Lampiran/View', [
            'doc' => $doc,
            'signers' => $signers,
            'hasSigned' => $hasSigned,
            'currentUserId' => $currentUserId,
        ]);
    }

    public function signLampiran(Request $request, $lampiranId)
    {
        $lampiran = NotaLampiran::findOrFail($lampiranId);
        $this->authorizeLampiranOrAbort($lampiran);
        $ids = array_map('strval', $lampiran->signature_user_ids ?? []);
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('nota.lampiran.view', $lampiranId)->with('error', 'Pengguna tidak terautentikasi.');
        }

        $currentUserId = (string) (Auth::id());
        $hasSigned = in_array($currentUserId, $ids, true);
        if ($hasSigned) {
            return redirect()->route('nota.lampiran.view', $lampiran->id);
        }

        $data = $request->validate([
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

        $options = [
            'signer_id' => optional($user)->nik,
            'method' => $data['method'] ?? 'passphrase',
            'passphrase' => $data['passphrase'] ?? null,
            'totp' => $data['totp'] ?? null,
            'tampilan' => $data['tampilan'] ?? null,
            'imageBase64' => $data['imageBase64'] ?? null,
            'signature_path' => $data['signature_path'] ?? null,
            'page' => $data['page'] ?? null,
            'originX' => $data['originX'] ?? null,
            'originY' => $data['originY'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'tag_koordinat' => $data['tag_koordinat'] ?? null,
            'location' => $data['location'] ?? null,
            'reason' => $data['reason'] ?? null,
            'pdfPassword' => $data['pdfPassword'] ?? null,
        ];

        $service = app(EsignSignerService::class);
        $result = $service->signLampiran($user, $lampiran, $options);

        if (! ($result['success'] ?? false)) {
            return redirect()->route('nota.lampiran.view', $lampiranId)->with('error', $result['message'] ?? 'Gagal menghubungkan layanan eSign.');
        }

        $path = $result['paths'][0] ?? null;
        if (! $path) {
            return redirect()->route('nota.lampiran.view', $lampiranId)->with('error', 'Path hasil tanda tangan tidak tersedia.');
        }

        $userId = (string) $user->id;
        $ids = $lampiran->signature_user_ids ?? [];
        if (! in_array($userId, $ids, true)) {
            $lampiran->addSignatureUserId($userId, $path)->save();
        }

        return redirect()->route('nota.lampiran.view', $lampiranId)->with('success', 'Dokumen berhasil ditandatangani.');
    }

    public function signPage($lampiranId)
    {
        $lampiran = NotaLampiran::findOrFail($lampiranId);
        $this->authorizeLampiranOrAbort($lampiran);
        $ids = array_map('strval', $lampiran->signature_user_ids ?? []);
        $signers = User::whereIn('id', $ids)->select('id', 'name')->get()->map(function ($u) {
            return ['id' => (string) $u->id, 'name' => $u->name];
        })->values();
        $currentUserId = (string) (Auth::id());
        $hasSigned = in_array($currentUserId, $ids, true);
        if ($hasSigned) {
            return redirect()->route('nota.lampiran.view', $lampiran->id);
        }
        $latestSignature = $lampiran->signatures()->orderBy('created_at', 'desc')->first();
        $useSigned = false;
        if ($latestSignature) {
            $path = (string) $latestSignature->path;
            if ($path && Storage::disk('local')->exists($path)) {
                $useSigned = true;
                Log::info('nota.lampiran.sign.use_signed', ['lampiran_id' => $lampiran->id, 'path' => $path]);
            } else {
                Log::warning('nota.lampiran.sign.missing_signed', ['lampiran_id' => $lampiran->id, 'path' => $path]);
            }
        }
        if (! $useSigned) {
            $origPath = (string) $lampiran->path;
            if ($origPath && Storage::disk('local')->exists($origPath)) {
                Log::info('nota.lampiran.sign.use_original', ['lampiran_id' => $lampiran->id, 'path' => $origPath]);
            } else {
                Log::warning('nota.lampiran.sign.missing_original', ['lampiran_id' => $lampiran->id, 'path' => $origPath]);
            }
        }
        $doc = [
            'id' => $lampiran->id,
            'name' => $lampiran->nama_file,
            'url' => $useSigned
                ? route('documents.download', ['lampiran' => $lampiran->id, 'type' => 'signed', 'version' => 'v1'])
                : route('documents.download', ['lampiran' => $lampiran->id, 'type' => 'original']),
            'nota_dinas_id' => $lampiran->nota_dinas_id,
        ];

        return Inertia::render('NotaDinas/Lampiran/Sign', [
            'doc' => $doc,
            'signers' => $signers,
            'hasSigned' => $hasSigned,
            'currentUserId' => $currentUserId,
            'currentUserSignaturePath' => optional(Auth::user())->signature_path,
            'currentUserNik' => optional(Auth::user())->nik,
        ]);
    }

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
        $latestPengiriman = NotaPengiriman::where('nota_dinas_id', optional($nota)->id)
            ->whereHas('lampirans', function ($q) use ($lampiran) {
                $q->where('nota_lampirans.id', $lampiran->id);
            })
            ->orderBy('created_at', 'desc')
            ->first();

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

    public function lampiranStatus($lampiranId)
    {
        $lampiran = NotaLampiran::findOrFail($lampiranId);
        $this->authorizeLampiranOrAbort($lampiran);
        $ids = array_map('strval', $lampiran->signature_user_ids ?? []);
        $currentUserId = (string) (Auth::id());
        $hasSigned = in_array($currentUserId, $ids, true);

        return response()->json([
            'hasSigned' => $hasSigned,
            'signers' => User::whereIn('id', $ids)->select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nomor_nota' => 'required|string|max:255',
            'perihal' => 'required|string|max:255',
            'anggaran' => 'nullable|numeric',
            'tanggal_pengajuan' => 'required|date',
            // 'lampiran.*' => 'nullable|file|mimes:pdf|max:2048',
        ]);

        $nota = NotaDinas::create([
            'skpd_id' => auth()->user()->skpd_id,
            'nomor_nota' => $validated['nomor_nota'],
            'perihal' => $validated['perihal'],
            'anggaran' => $validated['anggaran'],
            'tanggal_pengajuan' => $validated['tanggal_pengajuan'],
            'status' => 'draft',
            'tahap_saat_ini' => 'skpd',
            'asisten_id' => auth()->user()->skpd->asisten_id ?? null,
        ]);

        return redirect()->route('nota-dinas.index')
            ->with('success', 'Nota berhasil dibuat.');
    }

    public function update(Request $request, NotaDinas $notaDina)
    {
        if (! in_array($notaDina->status, ['draft', 'dikembalikan'])) {
            return redirect()->route('nota-dinas.index')
                ->with('error', 'Nota hanya bisa diperbarui jika berstatus draft atau dikembalikan.');
        }

        $validated = $request->validate([
            'nomor_nota' => 'required|string|max:255',
            'perihal' => 'required|string|max:255',
            'anggaran' => 'nullable|numeric',
            'tanggal_pengajuan' => 'required|date',
            // 'lampiran.*' => 'nullable|file|mimes:pdf|max:2048',
        ]);

        DB::transaction(function () use ($notaDina, $validated) {
            $notaDina->update([
                'nomor_nota' => $validated['nomor_nota'],
                'perihal' => $validated['perihal'],
                'anggaran' => $validated['anggaran'],
                'tanggal_pengajuan' => $validated['tanggal_pengajuan'],
                'asisten_id' => auth()->user()->skpd->asisten_id ?? null,
            ]);

            /* Uncomment and update if handling lampiran files in update
            if ($request->hasFile('lampiran')) {
                foreach ($request->file('lampiran') as $file) {
                    $path = $file->store('lampiran_nota', 'public');

                    NotaLampiran::create([
                        'nota_dinas_id' => $notaDina->id,
                        'nama_file' => $file->getClientOriginalName(),
                        'path' => $path,
                    ]);
                }
            }
            */
        });
        sleep(1);

        return redirect()->route('nota-dinas.index')
            ->with('success', 'Nota berhasil diperbarui.');
    }

    public function destroy(NotaDinas $notaDina)
    {
        if (! in_array($notaDina->status, ['draft', 'dikembalikan'])) {
            return redirect()->route('nota-dinas.index')
                ->with('error', 'Nota hanya bisa dihapus jika berstatus draft atau dikembalikan.');
        }
        foreach ($notaDina->lampirans as $lampiran) {
            Storage::delete('storage/'.$lampiran->path);
        }
        $notaDina->delete();

        return redirect()->route('nota-dinas.index')
            ->with('success', 'Nota berhasil dihapus');
    }

    public function getLampiran($id)
    {
        $notaDinas = NotaDinas::with('lampirans')->findOrFail($id);

        $lampirans = $notaDinas->lampirans
            ->sortByDesc('created_at')
            ->map(function ($lampiran) {
                return [
                    'id' => $lampiran->id,
                    'name' => $lampiran->nama_file,
                    'url' => route('documents.download', ['lampiran' => $lampiran->id, 'type' => 'original']),
                    'created_at' => $lampiran->created_at,
                ];
            })->values();

        return response()->json([
            'success' => true,
            'data' => $lampirans,
        ]);
    }

    public function getLampiranHistori($tipe, $id)
    {
        $pengiriman = NotaPengiriman::findOrFail($id);
        $lampirans = $pengiriman->lampirans->map(function ($lampiran) {
            return [
                'name' => $lampiran->nama_file,
                'url' => route('documents.download', ['lampiran' => $lampiran->id, 'type' => 'original']),
                'created_at' => $lampiran->created_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $lampirans,
        ]);
    }

    public function approveOrRejectNota(Request $request, $notaId)
    {
        if (auth()->user()->role !== 'bupati') {
            return redirect()->route('nota-dinas.index')
                ->with('error', 'Hanya Bupati yang dapat menyetujui atau menolak Nota Dinas.');
        }
        // sleep(5);
        $notaDinas = NotaDinas::findOrFail($notaId);
        $status = $request->status === 'disetujui' ? 'disetujui' : 'ditolak';

        $catatan = $request->filled('catatan')
            ? $request->catatan
            : "Nota telah {$status} oleh Bupati.";

        $notaDinas->update([
            'status' => $status,
            'tahap_saat_ini' => 'selesai',
        ]);

        NotaPengiriman::create([
            'nota_dinas_id' => $notaDinas->id,
            'dikirim_dari' => 'bupati',
            'dikirim_ke' => 'selesai',
            'pengirim_id' => auth()->user()->id,
            'tanggal_kirim' => now(),
            'catatan' => $catatan,
        ]);

        NotaPersetujuan::create([
            'nota_dinas_id' => $notaDinas->id,
            'approver_id' => auth()->user()->id,
            'skpd_id' => $notaDinas->skpd_id,
            'role_approver' => auth()->user()->role,
            'urutan' => 3,
            'status' => $status,
            'catatan_terakhir' => $catatan,
            'tanggal_update' => now(),
        ]);

        return redirect()->route('nota-dinas.index')
            ->with('success', "Nota telah {$status} dan diperbarui.");
    }
}
