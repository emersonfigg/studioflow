<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_movements', 'direction')) {
                $table->string('direction', 10)->nullable()->after('type');
            }
            if (! Schema::hasColumn('stock_movements', 'balance_before')) {
                $table->decimal('balance_before', 10, 2)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('stock_movements', 'balance_after')) {
                $table->decimal('balance_after', 10, 2)->nullable()->after('balance_before');
            }
            if (! Schema::hasColumn('stock_movements', 'reference_type')) {
                $table->string('reference_type')->nullable()->after('source_id');
            }
            if (! Schema::hasColumn('stock_movements', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            }
            if (! Schema::hasColumn('stock_movements', 'notes')) {
                $table->text('notes')->nullable()->after('reason');
            }
            if (! Schema::hasColumn('stock_movements', 'movement_date')) {
                $table->timestamp('movement_date')->nullable()->after('occurred_at');
            }
        });

        DB::table('stock_movements')->whereNull('direction')->orderBy('id')->chunkById(200, function ($movements): void {
            foreach ($movements as $movement) {
                $direction = match ($movement->type) {
                    'in' => 'in',
                    'sale', 'out', 'service_consumption' => 'out',
                    default => ((float) $movement->new_quantity >= (float) $movement->previous_quantity ? 'in' : 'out'),
                };

                DB::table('stock_movements')
                    ->where('id', $movement->id)
                    ->update([
                        'direction' => $direction,
                        'balance_before' => $movement->previous_quantity,
                        'balance_after' => $movement->new_quantity,
                        'reference_type' => $movement->source_type,
                        'reference_id' => $movement->source_id,
                        'movement_date' => $movement->occurred_at,
                    ]);
            }
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['company_id', 'direction'], 'stock_mov_company_direction_idx');
            $table->index(['reference_type', 'reference_id'], 'stock_mov_reference_idx');
            $table->index(['company_id', 'movement_date'], 'stock_mov_company_movement_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_mov_company_direction_idx');
            $table->dropIndex('stock_mov_reference_idx');
            $table->dropIndex('stock_mov_company_movement_date_idx');

            foreach (['movement_date', 'notes', 'reference_id', 'reference_type', 'balance_after', 'balance_before', 'direction'] as $column) {
                if (Schema::hasColumn('stock_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
