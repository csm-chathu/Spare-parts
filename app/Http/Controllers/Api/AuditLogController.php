<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }

        $logs = AuditLog::with('user:id,name')
            ->when($request->action, fn($q, $a) => $q->whereRaw('MATCH(action) AGAINST(? IN BOOLEAN MODE)', ["$a*"]))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->user_id, fn($q, $u) => $q->where('user_id', $u))
            ->latest()
            ->paginate($request->per_page ?? 50);

        return response()->json($logs);
    }
}
