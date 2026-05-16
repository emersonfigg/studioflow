<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('payment_status', 20)->nullable()->after('status');
            $table->string('payment_gateway', 40)->nullable()->after('payment_status');
            $table->string('payment_reference')->nullable()->after('payment_gateway');
            $table->string('payment_preference_id')->nullable()->after('payment_reference');
            $table->string('payment_external_id')->nullable()->after('payment_preference_id');
            $table->decimal('amount_total', 10, 2)->default(0)->after('payment_external_id');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('amount_total');
            $table->decimal('deposit_amount', 10, 2)->default(0)->after('amount_paid');
            $table->timestamp('payment_expires_at')->nullable()->after('deposit_amount');
            $table->timestamp('confirmed_at')->nullable()->after('payment_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_status',
                'payment_gateway',
                'payment_reference',
                'payment_preference_id',
                'payment_external_id',
                'amount_total',
                'amount_paid',
                'deposit_amount',
                'payment_expires_at',
                'confirmed_at',
            ]);
        });
    }
};
