<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return Account::with('parent:id,code,name')
            ->when($request->type,   fn($q, $t) => $q->where('type', $t))
            ->when($request->search, fn($q, $s) => $q->where(function ($inner) use ($s) {
                $inner->whereRaw('MATCH(name) AGAINST(? IN BOOLEAN MODE)', ["$s*"])
                      ->orWhere('code', 'like', "$s%");
            }))
            ->orderBy('code')
            ->get();
    }

    public function all()
    {
        return Account::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'sub_type']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|unique:accounts,code',
            'name'        => 'required|string|max:200',
            'type'        => 'required|in:asset,liability,equity,revenue,expense',
            'sub_type'    => 'nullable|string|max:50',
            'parent_id'   => 'nullable|exists:accounts,id',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        return response()->json(Account::create($data), 201);
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|unique:accounts,code,' . $account->id,
            'name'        => 'required|string|max:200',
            'type'        => 'required|in:asset,liability,equity,revenue,expense',
            'sub_type'    => 'nullable|string|max:50',
            'parent_id'   => 'nullable|exists:accounts,id',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        // Prevent changing type of system accounts
        if ($account->is_system) {
            $data['type'] = $account->type;
        }

        $account->update($data);
        return response()->json($account->fresh('parent'));
    }

    public function destroy(Account $account)
    {
        if ($account->is_system) {
            return response()->json(['message' => 'System accounts cannot be deleted.'], 422);
        }

        if ($account->lines()->exists()) {
            return response()->json(['message' => 'Account has posted transactions and cannot be deleted.'], 422);
        }

        $account->delete();
        return response()->json(['message' => 'Account deleted.']);
    }
}
