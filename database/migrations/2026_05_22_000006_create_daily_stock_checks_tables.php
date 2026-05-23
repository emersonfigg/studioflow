<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stock_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('reference_date');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'reference_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('daily_stock_check_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_stock_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('sold_quantity', 10, 2)->default(0);
            $table->decimal('sale_stock_quantity', 10, 2)->default(0);
            $table->decimal('other_output_quantity', 10, 2)->default(0);
            $table->decimal('input_quantity', 10, 2)->default(0);
            $table->decimal('adjustment_quantity', 10, 2)->default(0);
            $table->decimal('expected_quantity', 10, 2)->nullable();
            $table->decimal('counted_quantity', 10, 2)->nullable();
            $table->decimal('difference_quantity', 10, 2)->nullable();
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('difference_value', 10, 2)->nullable();
            $table->string('status', 20)->nullable();
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamp('adjusted_at')->nullable();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['daily_stock_check_id', 'product_id'], 'daily_stock_check_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stock_check_items');
        Schema::dropIfExists('daily_stock_checks');
    }
};
