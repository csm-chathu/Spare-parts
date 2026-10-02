<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // Drop the existing FK constraint so we can make it nullable
            $table->dropForeign(['product_id']);
            $table->foreignId('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();

            $table->string('type', 20)->default('part')->after('sale_id');
            $table->string('description')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['type', 'description']);
            $table->dropForeign(['product_id']);
            $table->foreignId('product_id')->change();
            $table->foreign('product_id')->references('id')->on('products');
        });
    }
};
