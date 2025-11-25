<?php

namespace App\Http\Controllers;

use App\Http\Requests\EsignActivationRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Skpd;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            // 'skpds' => Skpd::where('status', true)->select('id', 'nama_skpd')->get()
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill($validated);

        if ($request->hasFile('signature')) {
            $file = $request->file('signature');
            $path = $file->store('signatures', 'public');
            $user->signature_path = $path;
        }

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function updateEsign(EsignActivationRequest $request): RedirectResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $user->nik = $validated['nik'];

            if ($request->hasFile('signature')) {
                $path = $request->file('signature')->store('signatures', 'public');
                $user->signature_path = $path;
            }

            $user->save();

            return Redirect::route('profile.edit')->with('status', 'esign-updated');
        } catch (\Throwable $e) {
            return Redirect::back()->withErrors(['nik' => 'Gagal menyimpan data eSign.'])->with('error', $e->getMessage());
        }
    }
}
