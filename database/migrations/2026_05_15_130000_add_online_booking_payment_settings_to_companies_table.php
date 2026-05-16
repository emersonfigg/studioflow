<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('online_booking_payment_enabled')->default(false)->after('auto_print_receipt');
            $table->string('booking_payment_mode', 20)->default('none')->after('online_booking_payment_enabled');
            $table->string('booking_deposit_type', 20)->nullable()->after('booking_payment_mode');
            $table->decimal('booking_deposit_value', 10, 2)->nullable()->after('booking_deposit_type');
            $table->unsignedInteger('booking_payment_expiration_minutes')->default(15)->after('booking_deposit_value');
            $table->boolean('booking_auto_cancel_unpaid')->default(true)->after('booking_payment_expiration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'online_booking_payment_enabled',
                'booking_payment_mode',
                'booking_deposit_type',
                'booking_deposit_value',
                'booking_payment_expiration_minutes',
                'booking_auto_cancel_unpaid',
            ]);
        });
    }
};
