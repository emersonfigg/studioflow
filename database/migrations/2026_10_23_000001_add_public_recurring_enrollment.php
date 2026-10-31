<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_public_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('email_hash', 64);
            $table->text('email');
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'email_hash'], 'membership_identity_email_unique');
            $table->unique(['company_id', 'id'], 'membership_identity_company_unique');
            $table->foreign(['company_id', 'client_id'], 'membership_identity_client_company_fk')->references(['company_id', 'id'])->on('clients')->restrictOnDelete();
        });
        Schema::create('membership_identity_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('identity_id')->constrained('membership_public_identities')->restrictOnDelete();
            $table->string('session_hash', 64);
            $table->string('code_hash', 64);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('membership_checkout_intentions', function (Blueprint $table) {
            $table->foreignId('public_identity_id')->nullable()->constrained('membership_public_identities')->restrictOnDelete();
            $table->foreignId('membership_plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('external_customer_id')->nullable();
            $table->timestamp('creation_started_at')->nullable();
            $table->timestamp('customer_request_started_at')->nullable();
            $table->timestamp('checkout_request_started_at')->nullable();
            $table->unique(['public_identity_id', 'membership_plan_id'], 'membership_enrollment_once');
            $table->unique(['payment_account_id', 'external_customer_id'], 'membership_intention_customer_unique');
            $table->foreign(['company_id', 'public_identity_id'], 'membership_intention_identity_company_fk')->references(['company_id', 'id'])->on('membership_public_identities')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new LogicException('Additive financial migration: rollback requires a reviewed preservation plan.');
    }
};
