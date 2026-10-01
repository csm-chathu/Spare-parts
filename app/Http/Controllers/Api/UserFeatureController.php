<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserFeatureController extends Controller
{
    /** Features for the currently authenticated user (used by nav). */
    public function index(Request $request)
    {
        $user = $request->user();

        $hasOverrides = DB::table('user_features')->where('user_id', $user->id)->exists();

        if ($hasOverrides) {
            $features = DB::table('features')
                ->join('user_features', 'features.id', '=', 'user_features.feature_id')
                ->where('user_features.user_id', $user->id)
                ->where('user_features.can_view', true)
                ->orderBy('features.sort_order')
                ->select('features.key', 'features.label', 'features.route', 'features.icon', 'features.group')
                ->get();
        } else {
            $features = DB::table('features')
                ->join('role_features', 'features.id', '=', 'role_features.feature_id')
                ->where('role_features.role', $user->role)
                ->where('role_features.can_view', true)
                ->orderBy('features.sort_order')
                ->select('features.key', 'features.label', 'features.route', 'features.icon', 'features.group')
                ->get();
        }

        return response()->json($features);
    }

    /** All features with whether each is enabled for a given role — used when populating the Add/Edit User form. */
    public function byRole(Request $request, string $role)
    {
        if (!$request->user()->isAdmin()) abort(403);

        $features = DB::table('features')
            ->leftJoin('role_features', function ($join) use ($role) {
                $join->on('features.id', '=', 'role_features.feature_id')
                     ->where('role_features.role', $role);
            })
            ->orderBy('features.sort_order')
            ->select(
                'features.id',
                'features.key',
                'features.label',
                'features.group',
                'features.icon',
                DB::raw('CASE WHEN role_features.can_view = 1 THEN 1 ELSE 0 END as enabled')
            )
            ->get();

        return response()->json($features);
    }

    /** All features with whether each is enabled for a specific user — used when opening Edit User. */
    public function forUser(Request $request, int $userId)
    {
        if (!$request->user()->isAdmin()) abort(403);

        $user = \App\Models\User::findOrFail($userId);
        $hasOverrides = DB::table('user_features')->where('user_id', $userId)->exists();

        if ($hasOverrides) {
            $features = DB::table('features')
                ->leftJoin('user_features', function ($join) use ($userId) {
                    $join->on('features.id', '=', 'user_features.feature_id')
                         ->where('user_features.user_id', $userId);
                })
                ->orderBy('features.sort_order')
                ->select(
                    'features.id',
                    'features.key',
                    'features.label',
                    'features.group',
                    'features.icon',
                    DB::raw('CASE WHEN user_features.can_view = 1 THEN 1 ELSE 0 END as enabled')
                )
                ->get();
        } else {
            // Fall back to role defaults
            $features = DB::table('features')
                ->leftJoin('role_features', function ($join) use ($user) {
                    $join->on('features.id', '=', 'role_features.feature_id')
                         ->where('role_features.role', $user->role);
                })
                ->orderBy('features.sort_order')
                ->select(
                    'features.id',
                    'features.key',
                    'features.label',
                    'features.group',
                    'features.icon',
                    DB::raw('CASE WHEN role_features.can_view = 1 THEN 1 ELSE 0 END as enabled')
                )
                ->get();
        }

        return response()->json($features);
    }

    /** Save per-user feature overrides. */
    public function saveForUser(Request $request, int $userId)
    {
        if (!$request->user()->isAdmin()) abort(403);

        $request->validate([
            'feature_ids'   => 'present|array',
            'feature_ids.*' => 'integer|exists:features,id',
        ]);

        $enabledIds = collect($request->feature_ids);

        $allFeatures = DB::table('features')->pluck('id');

        DB::transaction(function () use ($userId, $enabledIds, $allFeatures) {
            DB::table('user_features')->where('user_id', $userId)->delete();

            $rows = $allFeatures->map(fn($id) => [
                'user_id'    => $userId,
                'feature_id' => $id,
                'can_view'   => $enabledIds->contains($id) ? 1 : 0,
            ])->all();

            DB::table('user_features')->insert($rows);
        });

        return response()->json(['message' => 'Features saved']);
    }
}
