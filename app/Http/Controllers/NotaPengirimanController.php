<?php

namespace App\Http\Controllers;

use App\Models\NotaDinas;
use App\Models\NotaLampiran;
use App\Models\NotaPengiriman;
use App\Models\NotaPersetujuan;
use App\Services\SignatureDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class NotaPengirimanController extends Controller
{
    public function store(Request $request, $notaId)
    {
        $nota = NotaDinas::findOrFail($notaId);
        $pengirim = Auth::user();

        $request->validate([
            'catatan' => 'nullable|string|max:500',
            'lampiran.*' => 'nullable|file|mimes:pdf|max:4096',
            'dikirim_ke' => 'nullable|in:asisten,sekda,bupati,skpd',
        ]);

        $dari = $nota->tahap_saat_ini;
        if ($dari === 'skpd') {
            if (! optional($nota->skpd)->asisten_id) {
                return redirect()->back()->with('error', 'SKPD belum memiliki Asisten. Silakan minta admin menetapkan Asisten untuk SKPD ini.');
            }
            $ke = 'asisten';
        } elseif ($dari === 'asisten') {
            $ke = 'sekda';
        } elseif ($dari === 'sekda') {
            $ke = 'bupati';
        } else {
            $ke = $request->dikirim_ke ?? abort(400, 'Tujuan pengiriman tidak valid.');
        }

        $pengiriman = NotaPengiriman::create([
            'nota_dinas_id' => $nota->id,
            'dikirim_dari' => $dari,
            'dikirim_ke' => $ke,
            'pengirim_id' => $pengirim->id,
            'catatan' => $request->catatan,
        ]);

        $lampiranIds = [];

        if ($request->hasFile('lampiran')) {
            $svc = app(SignatureDocumentService::class);
            foreach ($request->file('lampiran') as $file) {
                try {
                    $lampiran = NotaLampiran::create([
                        'nota_dinas_id' => $nota->id,
                        'nama_file' => $file->getClientOriginalName(),
                        'path' => '',
                    ]);
                    $stored = $svc->storeOriginal($lampiran->id, $file);
                    $lampiran->path = $stored['path'];
                    $lampiran->save();
                    Log::info('lampiran.uploaded', ['nota_id' => $nota->id, 'lampiran_id' => $lampiran->id, 'path' => $stored['path']]);
                    $lampiranIds[] = $lampiran->id;
                } catch (\Throwable $e) {
                    Log::error('lampiran.upload_failed', ['nota_id' => $nota->id, 'error' => $e->getMessage()]);

                    return redirect()->back()->with('error', 'Gagal mengunggah lampiran: '.$e->getMessage());
                }
            }
        } else {
            $prevPengiriman = NotaPengiriman::where('nota_dinas_id', $nota->id)
                ->where('id', '<', $pengiriman->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($prevPengiriman) {
                $lampiranIds = $prevPengiriman->lampirans()->pluck('nota_lampirans.id')->toArray();
            }
        }

        if (! empty($lampiranIds)) {
            $pengiriman->lampirans()->attach($lampiranIds);
        }

        if (in_array($pengirim->role, ['asisten', 'sekda'])) {
            NotaPersetujuan::create([
                'nota_dinas_id' => $nota->id,
                'approver_id' => $pengirim->id,
                'skpd_id' => $nota->skpd_id,
                'role_approver' => $pengirim->role,
                'urutan' => $pengirim->role === 'asisten' ? 1 : 2,
                'status' => 'disetujui',
                'catatan_terakhir' => $request->catatan,
                'tanggal_update' => now(),
            ]);
        }

        $nota->update([
            'tahap_saat_ini' => $ke,
            'status' => 'proses',
        ]);

        if (! in_array(auth()->user()->role, ['asisten', 'sekda', 'bupati'])) {
            return redirect()
                ->back()
                ->with('success', 'Nota berhasil dikirim ke '.ucfirst($ke).' dan menunggu persetujuan.');
        } else {
            return redirect()
                ->back()
                ->with('success', 'Nota berhasil dikirim ke '.ucfirst($ke).' dan dicatat sebagai persetujuan anda.');
        }

    }

    public function history($id)
    {
        $nota = NotaDinas::findOrFail($id);
        $pengiriman = $nota->pengirimans()->with(['pengirim'])->latest('tanggal_kirim')->get();

        return Inertia::render('NotaDinas/HistoriPengiriman', [
            'nota' => $nota,
            'pengiriman' => $pengiriman,
        ]);
    }

    public function returnNota(Request $request)
    {
        if (! in_array(auth()->user()->role, ['asisten', 'sekda', 'bupati'])) {
            return abort(403, 'Akses tidak diizinkan');
        }

        $request->validate([
            'catatan' => 'required|string|max:500',
            'nota_dinas_id' => 'required|exists:nota_dinas,id',
        ]);

        $notaDinas = NotaDinas::findOrFail($request->nota_dinas_id);

        NotaPengiriman::create([
            'nota_dinas_id' => $notaDinas->id,
            'dikirim_dari' => auth()->user()->role,
            'dikirim_ke' => 'skpd',
            'pengirim_id' => auth()->user()->id,
            'tanggal_kirim' => now(),
            'catatan' => $request->catatan,
        ]);

        $pengirim = Auth::user();
        if (in_array($pengirim->role, ['asisten', 'sekda', 'bupati'])) {
            NotaPersetujuan::create([
                'nota_dinas_id' => $notaDinas->id,
                'approver_id' => $pengirim->id,
                'skpd_id' => $notaDinas->skpd_id,
                'role_approver' => $pengirim->role,
                'urutan' => $pengirim->role === 'asisten' ? 1 : 2,
                'status' => 'dikembalikan',
                'catatan_terakhir' => $request->catatan,
                'tanggal_update' => now(),
            ]);
        }

        $notaDinas->update([
            'status' => 'dikembalikan',
            'tahap_saat_ini' => 'skpd',
        ]);

        return redirect()->route('nota-dinas.index')->with('success', 'Nota telah dikembalikan ke SKPD.');
    }
}
