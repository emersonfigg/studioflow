<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'stock_quantity_decimal')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->decimal('stock_quantity_decimal', 10, 2)->default(0)->after('price');
            });

            DB::table('products')->orderBy('id')->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('products')
                        ->where('id', $row->id)
                        ->update(['stock_quantity_decimal' => (float) $row->stock_quantity]);
                }
            });

            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('stock_quantity');
            });

            Schema::table('products', function (Blueprint $table): void {
                $table->renameColumn('stock_quantity_decimal', 'stock_quantity');
            });
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'minimum_stock')) {
                $table->decimal('minimum_stock', 10, 2)->nullable()->after('stock_quantity');
            }
            if (! Schema::hasColumn('products', 'cost_price')) {
                $table->decimal('cost_price', 10, 2)->nullable()->after('minimum_stock');
            }
            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 40)->nullable()->after('cost_price');
            }
            if (! Schema::hasColumn('products', 'track_stock')) {
                $table->boolean('track_stock')->default(true)->after('unit');
            }
            if (! Schema::hasColumn('products', 'low_stock_alert')) {
                $table->boolean('low_stock_alert')->default(true)->after('track_stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach (['minimum_stock', 'cost_price', 'unit', 'track_stock', 'low_stock_alert'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('products', 'stock_quantity') && Schema::getColumnType('products', 'stock_quantity') !== 'integer') {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedInteger('stock_quantity_int')->default(0)->after('price');
            });

            DB::table('products')->orderBy('id')->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('products')
                        ->where('id', $row->id)
                        ->update(['stock_quantity_int' => (int) floor((float) $row->stock_quantity)]);
                }
            });

            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('stock_quantity');
            });

            Schema::table('products', function (Blueprint $table): void {
                $table->renameColumn('stock_quantity_int', 'stock_quantity');
            });
        }
    }
};
