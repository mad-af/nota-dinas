<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PublicDocumentAccess
{
    public function handle(Request $request, Closure $next)
    {
        $id = (int) $request->route('id');
        $token = (string) $request->query('token');
        if ($id < 1 || ! $token) {
            abort(410);
        }
        try {
            $json = Crypt::decryptString($token);
            $data = json_decode($json, true);
        } catch (\Throwable $e) {
            abort(410);
        }
        if (! is_array($data)) {
            abort(410);
        }
        $tid = (int) ($data['id'] ?? 0);
        $exp = (int) ($data['exp'] ?? 0);
        if ($tid !== $id) {
            abort(410);
        }
        if ($exp < now()->timestamp) {
            abort(410);
        }

        return $next($request);
    }
}
