<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'service_order_id')) {
                $table->foreignId('service_order_id')
                    ->nullable()
                    ->after('appointment_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });

        Schema::table('product_sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_sales', 'service_order_id')) {
                $table->foreignId('service_order_id')
                    ->nullable()
                    ->after('appointment_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_sales', function (Blueprint $table): void {
            if (Schema::hasColumn('product_sales', 'service_order_id')) {
                $table->dropConstrainedForeignId('service_order_id');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'service_order_id')) {
                $table->dropConstrainedForeignId('service_order_id');
            }
        });
    }
};
