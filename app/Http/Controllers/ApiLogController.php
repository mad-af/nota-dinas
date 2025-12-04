<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiLogController extends Controller
{
    public function index(Request $request)
    {
        $search = (string) $request->query('search', '');
        $method = (string) $request->query('method', '');
        $status = (string) $request->query('status', '');

        $q = ApiLog::query();
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('endpoint', 'like', '%'.$search.'%')
                    ->orWhere('error_message', 'like', '%'.$search.'%')
                    ->orWhere('correlation_id', 'like', '%'.$search.'%');
            });
        }
        if ($method !== '') {
            $q->where('method', $method);
        }
        if ($status !== '') {
            $q->where('status_code', (int) $status);
        }

        $logs = $q->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('ApiLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
                'method' => $method,
                'status' => $status,
            ],
        ]);
    }
}

