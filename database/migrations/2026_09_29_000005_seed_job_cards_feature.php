<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('features')->where('key', 'job-cards')->first();
        if ($existing) return;

        $id = DB::table('features')->insertGetId([
            'key'        => 'job-cards',
            'label'      => 'Job Cards',
            'route'      => '/job-cards',
            'icon'       => 'WrenchScrewdriverIcon',
            'group'      => 'general',
            'sort_order' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roles = ['admin', 'manager', 'accountant', 'hr', 'finance', 'cashier', 'branch', 'auditor'];
        DB::table('role_features')->insert(
            array_map(fn($role) => ['role' => $role, 'feature_id' => $id, 'can_view' => true], $roles)
        );
    }

    public function down(): void
    {
        $feature = DB::table('features')->where('key', 'job-cards')->first();
        if ($feature) {
            DB::table('role_features')->where('feature_id', $feature->id)->delete();
            DB::table('features')->where('id', $feature->id)->delete();
        }
    }
};
