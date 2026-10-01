<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->unique()->after('id');
        });

        // Backfill existing rows
        DB::table('job_cards')->whereNull('public_token')->get()->each(function ($row) {
            DB::table('job_cards')->where('id', $row->id)->update(['public_token' => Str::uuid()]);
        });

        Schema::table('job_cards', function (Blueprint $table) {
            $table->uuid('public_token')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
