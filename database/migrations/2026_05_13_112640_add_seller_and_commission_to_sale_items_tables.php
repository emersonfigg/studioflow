<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_order_items', 'seller_id')) {
                $table->foreignId('seller_id')->nullable()->after('professional_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('product_sale_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_sale_items', 'seller_id')) {
                $table->foreignId('seller_id')->nullable()->after('product_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('product_sale_items', 'commission_type_snapshot')) {
                $table->string('commission_type_snapshot', 20)->nullable()->after('total_price');
            }
            if (! Schema::hasColumn('product_sale_items', 'commission_value_snapshot')) {
                $table->decimal('commission_value_snapshot', 10, 2)->nullable()->after('commission_type_snapshot');
            }
            if (! Schema::hasColumn('product_sale_items', 'commission_amount')) {
                $table->decimal('commission_amount', 10, 2)->default(0)->after('commission_value_snapshot');
            }
        });

        Schema::table('product_sale_items', function (Blueprint $table): void {
            $table->index(['seller_id', 'created_at'], 'product_sale_items_seller_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_sale_items', function (Blueprint $table): void {
            try {
                $table->dropIndex('product_sale_items_seller_created_idx');
            } catch (\Throwable) {
                // index may not exist when rolling back partial setups
            }

            if (Schema::hasColumn('product_sale_items', 'commission_amount')) {
                $table->dropColumn('commission_amount');
            }
            if (Schema::hasColumn('product_sale_items', 'commission_value_snapshot')) {
                $table->dropColumn('commission_value_snapshot');
            }
            if (Schema::hasColumn('product_sale_items', 'commission_type_snapshot')) {
                $table->dropColumn('commission_type_snapshot');
            }
            if (Schema::hasColumn('product_sale_items', 'seller_id')) {
                $table->dropConstrainedForeignId('seller_id');
            }
        });

        Schema::table('service_order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('service_order_items', 'seller_id')) {
                $table->dropConstrainedForeignId('seller_id');
            }
        });
    }
};
