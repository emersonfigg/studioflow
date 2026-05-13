<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_commercial_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_name_snapshot');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price_snapshot', 12, 2)->nullable();
            $table->decimal('total_amount_snapshot', 12, 2)->nullable();
            $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->dateTime('occurred_at');
            $table->unsignedSmallInteger('recommendation_days')->nullable();
            $table->date('next_recommendation_date')->nullable();
            $table->string('source', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'client_id'], 'cch_company_client_idx');
            $table->index(['company_id', 'item_type', 'item_id'], 'cch_company_item_idx');
            $table->index(['company_id', 'next_recommendation_date'], 'cch_company_next_idx');
            $table->index('sale_id', 'cch_sale_idx');
            $table->index('appointment_id', 'cch_appointment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_commercial_histories');
    }
};
