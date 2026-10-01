<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('route');
            $table->string('icon');
            $table->string('group')->default('general');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('role_features', function (Blueprint $table) {
            $table->id();
            $table->string('role', 50);
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(true);
            $table->unique(['role', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_features');
        Schema::dropIfExists('features');
    }
};
