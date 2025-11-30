<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EsignController;
use App\Http\Controllers\NotaDinasController;
use App\Http\Controllers\NotaPengirimanController;
use App\Http\Controllers\NotaPersetujuanController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\SignatureDocumentController;
use App\Http\Controllers\SkpdController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Auth/Login', [
        'canResetPassword' => Route::has('/'),
    ]);
})->middleware('guest')->name('/');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/esign', [ProfileController::class, 'updateEsign'])->name('profile.esign.update');
    Route::get('/nota-dinas/lampiran/{lampiran}', [NotaDinasController::class, 'viewLampiran'])->name('nota.lampiran.view');
    Route::post('/nota-dinas/lampiran/{lampiran}/sign', [NotaDinasController::class, 'signLampiran'])->name('nota.lampiran.sign');
    Route::get('/nota-dinas/lampiran/{lampiran}/tanda-tangan', [NotaDinasController::class, 'signPage'])->name('nota.lampiran.sign.page');
    Route::get('/nota-dinas/lampiran/{lampiran}/status', [NotaDinasController::class, 'lampiranStatus'])->name('nota.lampiran.status');
    Route::post('/nota-dinas/{nota}/kirim', [NotaPengirimanController::class, 'store'])->name('nota.pengiriman.store');
    Route::get('/nota/lampiran/{tipe}/{id}', [NotaDinasController::class, 'getLampiranHistori']);
    Route::resource('nota-dinas', NotaDinasController::class);
    Route::get('/nota/{id}/histori-pengiriman', [NotaPengirimanController::class, 'history'])->name('nota.pengiriman.history');
    Route::get('/nota/lampiran/{id}', [NotaDinasController::class, 'getLampiran']);
    Route::get('api/histori-persetujuan/{id}', [NotaPersetujuanController::class, 'approvalHistories']);
    Route::get('/approval-histories', [NotaPersetujuanController::class, 'index'])->name('approval-histories.index');
    Route::get('/nota-per-year', [DashboardController::class, 'getNotaPerYear']);
    Route::get('/approved-nota-dinas', [DashboardController::class, 'getApprovedNotaDinasBySkpd']);
    Route::get('/nota-dinas-stage', [DashboardController::class, 'getNotaDinasByStage']);

    Route::get('/esign/sign', [EsignController::class, 'showForm'])->name('esign.sign.show');
    Route::post('/esign/sign', [EsignController::class, 'submitSign'])->name('esign.sign.submit');
    Route::post('/esign/get-totp', [EsignController::class, 'requestTotp'])->name('esign.totp.request');

    Route::post('/documents/{lampiran}/original', [SignatureDocumentController::class, 'uploadOriginal'])->name('documents.original.upload');
    Route::post('/documents/{lampiran}/signed', [SignatureDocumentController::class, 'uploadSigned'])->name('documents.signed.upload');
    Route::get('/documents/{lampiran}/download', [SignatureDocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{lampiran}/manifest', [SignatureDocumentController::class, 'manifest'])->name('documents.manifest');
    Route::get('/documents/{lampiran}/versions', [SignatureDocumentController::class, 'versions'])->name('documents.versions');
    Route::post('/esign/verify', [EsignController::class, 'verifyPdf'])->name('esign.verify');
    Route::get('/esign/download/{filename}', [EsignController::class, 'downloadSigned'])->name('esign.download');

    // PDF Upload & Viewer
    Route::get('/pdf/upload-viewer', [PdfController::class, 'showUploadViewer'])->name('pdf.uploadViewer');
    Route::post('/pdf/upload', [PdfController::class, 'upload'])->name('pdf.upload');

});

Route::middleware(['auth'])->group(function () {
    Route::controller(DashboardController::class)->group(function () {
        Route::get('/dashboard', 'index')->name('dashboard');
    });

    Route::middleware(['role:admin'])->group(function () {
        Route::get('/users', [RegisteredUserController::class, 'index'])->name('users.index');
        Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/register', [RegisteredUserController::class, 'store']);
        Route::patch('/users/{user}/toggle-status', [RegisteredUserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::resource('skpds', SkpdController::class);
        Route::patch('skpds/{skpd}/toggle-status', [SkpdController::class, 'toggleStatus'])->name('skpds.toggle-status');
    });

    Route::middleware(['auth', 'role:bupati'])->group(function () {
        Route::patch('/nota-dinas/{nota}/approve', [NotaDinasController::class, 'approveOrRejectNota'])->name('nota-dinas.approval');
    });

});

Route::middleware(['auth', 'role:asisten,sekda,bupati'])->group(function () {
    Route::post('/nota-dinas/{nota}/kembalikan', [NotaPengirimanController::class, 'returnNota'])->name('nota.kembalikan');
});

require __DIR__.'/auth.php';

Route::get('/qr/{code}', [PublicDocumentController::class, 'qr'])->name('public.qr');
Route::get('/public/document/{token}', [PublicDocumentController::class, 'view'])->middleware('public.access')->name('public.document.view');
Route::get('/public/document/pdf/{token}', [PublicDocumentController::class, 'stream'])->middleware('public.access')->name('public.document.stream');
