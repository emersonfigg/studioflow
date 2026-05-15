<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_cycle', 32);
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('auto_renew')->default(false);
            $table->unsignedInteger('max_services_per_cycle')->nullable();
            $table->decimal('max_product_discount_percent', 5, 2)->nullable();
            $table->decimal('max_service_discount_percent', 5, 2)->nullable();
            $table->text('terms_text')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'active']);
        });

        Schema::create('membership_plan_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_per_cycle')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->boolean('included')->default(true);
            $table->timestamps();

            $table->unique(['membership_plan_id', 'service_id']);
            $table->index(['company_id', 'membership_plan_id']);
        });

        Schema::create('customer_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->string('status', 32);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->date('current_cycle_starts_at');
            $table->date('current_cycle_ends_at');
            $table->boolean('auto_renew')->default(false);
            $table->timestamp('accepted_terms_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'client_id', 'status']);
        });

        Schema::create('membership_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_membership_id')->constrained('customer_memberships')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained('service_orders')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('used_at');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('reference_type', 32);
            $table->unsignedBigInteger('reference_id');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'customer_membership_id', 'reference_type', 'reference_id', 'service_id'], 'membership_usages_idempotency');
            $table->index(['company_id', 'client_id']);
        });

        Schema::create('customer_no_shows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['company_id', 'client_id']);
        });

        Schema::create('customer_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('reason')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'client_id', 'active']);
        });

        Schema::create('appointment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedTinyInteger('service_quality_rating')->nullable();
            $table->unsignedTinyInteger('punctuality_rating')->nullable();
            $table->unsignedTinyInteger('environment_rating')->nullable();
            $table->boolean('private_feedback')->default(true);
            $table->string('token', 64)->nullable()->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reviews');
        Schema::dropIfExists('customer_blocks');
        Schema::dropIfExists('customer_no_shows');
        Schema::dropIfExists('membership_usages');
        Schema::dropIfExists('customer_memberships');
        Schema::dropIfExists('membership_plan_services');
        Schema::dropIfExists('membership_plans');
    }
};
