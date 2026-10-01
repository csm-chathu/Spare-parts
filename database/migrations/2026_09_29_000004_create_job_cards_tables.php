<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_number', 20)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('vehicle_number', 30)->nullable();
            $table->string('vehicle_make', 60)->nullable();
            $table->string('vehicle_model', 60)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->text('complaint')->nullable();
            $table->string('assigned_technician', 100)->nullable();
            $table->date('estimated_completion')->nullable();
            $table->enum('status', ['received', 'in_progress', 'completed', 'delivered', 'cancelled'])->default('received');
            $table->text('notes')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('job_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['part', 'labour', 'other'])->default('part');
            $table->string('description');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_card_items');
        Schema::dropIfExists('job_cards');
    }
};
