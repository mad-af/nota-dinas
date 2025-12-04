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

        $q = ApiLog::query()
            ->leftJoin('users', 'api_logs.user_id', '=', 'users.id')
            ->leftJoin('nota_lampirans', 'api_logs.correlation_id', '=', 'nota_lampirans.id')
            ->select('api_logs.*', 'users.name as user_name', 'nota_lampirans.nama_file as correlation_name');
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('api_logs.endpoint', 'like', '%'.$search.'%')
                    ->orWhere('api_logs.error_message', 'like', '%'.$search.'%')
                    ->orWhere('api_logs.correlation_id', 'like', '%'.$search.'%')
                    ->orWhere('nota_lampirans.nama_file', 'like', '%'.$search.'%')
                    ->orWhere('users.name', 'like', '%'.$search.'%');
            });
        }
        if ($method !== '') {
            $q->where('api_logs.method', $method);
        }
        if ($status !== '') {
            $q->where('api_logs.status_code', (int) $status);
        }

        $logs = $q->orderByDesc('api_logs.id')->paginate(20)->withQueryString();

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
