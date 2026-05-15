<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_payment_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('name')->nullable();
            $table->text('api_key')->nullable();
            $table->text('access_token')->nullable();
            $table->text('public_key')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('account_identifier')->nullable();
            $table->string('environment', 16)->default('production');
            $table->boolean('active')->default(true);
            $table->boolean('default_for_memberships')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'provider']);
            $table->index(['company_id', 'active', 'default_for_memberships']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->json('gateway_customer_refs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('gateway_customer_refs');
        });

        Schema::dropIfExists('company_payment_integrations');
    }
};
