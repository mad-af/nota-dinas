<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PdfController extends Controller
{
    public function showUploadViewer()
    {
        return Inertia::render('Pdf/UploadViewer');
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $file = $validated['file'];
        $path = $file->store('uploads/pdf', 'public');

        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'url' => Storage::url($path),
                'path' => $path,
            ],
        ]);
    }
}
