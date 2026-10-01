<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private const ROLES = ['admin', 'manager', 'accountant', 'hr', 'finance', 'cashier', 'branch', 'auditor', 'gold_buyer'];

    public function index(Request $request)
    {
        if (!$request->user()->isAdmin()) abort(403, 'Admin access required');

        $users = User::with('branch:id,name,code')
            ->when($request->search, fn($q, $s) => $q->where(function($inner) use ($s) {
                $inner->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%");
            }))
            ->when($request->branch_id, fn($q, $b) => $q->where('branch_id', $b))
            ->when($request->role, fn($q, $r) => $q->where('role', $r))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($users);
    }

    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) abort(403, 'Admin access required');

        $data = $request->validate([
            'name'                   => 'required|string|max:255',
            'username'               => 'required|string|max:50|alpha_dash|unique:users,username',
            'email'                  => 'required|email|unique:users,email',
            'password'               => 'required|string|min:6',
            'role'                   => 'required|in:' . implode(',', self::ROLES),
            'branch_id'              => 'nullable|exists:branches,id',
            'can_override_gold_rate' => 'boolean',
            'can_delete_transactions'=> 'boolean',
            'is_active'              => 'boolean',
        ]);

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        AuditLog::record('user_created', "Created user: {$user->name} ({$user->email})", $user);

        return response()->json($user->load('branch:id,name,code'), 201);
    }

    public function update(Request $request, User $user)
    {
        if (!$request->user()->isAdmin()) abort(403, 'Admin access required');

        $data = $request->validate([
            'name'                   => 'sometimes|string|max:255',
            'username'               => "sometimes|string|max:50|alpha_dash|unique:users,username,{$user->id}",
            'email'                  => "sometimes|email|unique:users,email,{$user->id}",
            'password'               => 'nullable|string|min:6',
            'role'                   => 'sometimes|in:' . implode(',', self::ROLES),
            'branch_id'              => 'nullable|exists:branches,id',
            'can_override_gold_rate' => 'boolean',
            'can_delete_transactions'=> 'boolean',
            'is_active'              => 'boolean',
        ]);

        $old = $user->only(['name','email','role','branch_id','can_override_gold_rate','can_delete_transactions','is_active']);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        AuditLog::record('user_updated', "Updated user: {$user->name}", $user, $old, $user->fresh()->only(array_keys($old)));

        return response()->json($user->load('branch:id,name,code'));
    }

    public function destroy(Request $request, User $user)
    {
        if (!$request->user()->isAdmin()) abort(403, 'Admin access required');
        if ($user->id === $request->user()->id) abort(422, 'Cannot delete your own account');

        AuditLog::record('user_deleted', "Deleted user: {$user->name} ({$user->email})", $user);
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }

    public function branches()
    {
        return response()->json(Branch::where('is_active', true)->get(['id','name','code']));
    }
}
