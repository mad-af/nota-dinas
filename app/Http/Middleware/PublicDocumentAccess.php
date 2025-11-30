<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PublicDocumentAccess
{
    public function handle(Request $request, Closure $next)
    {
        $token = (string) $request->route('token');
        if (! $token) {
            abort(404);
        }
        try {
            $json = Crypt::decryptString($token);
            $data = json_decode($json, true);
        } catch (\Throwable $e) {
            abort(404);
        }
        if (! is_array($data)) {
            abort(404);
        }
        $tid = (int) ($data['id'] ?? 0);
        $exp = (int) ($data['exp'] ?? 0);
        if ($tid < 1 || $exp < now()->timestamp) {
            abort(404);
        }

        return $next($request);
    }
}
