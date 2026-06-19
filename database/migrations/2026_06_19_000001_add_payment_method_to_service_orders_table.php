<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_orders', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('total')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('service_orders', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
