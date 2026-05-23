<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_count_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_count_items', 'adjusted_at')) {
                $table->timestamp('adjusted_at')->nullable()->after('adjustment_movement_id');
            }
            if (! Schema::hasColumn('stock_count_items', 'adjusted_by')) {
                $table->foreignId('adjusted_by')->nullable()->after('adjusted_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_count_items', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_count_items', 'adjusted_by')) {
                $table->dropConstrainedForeignId('adjusted_by');
            }
            if (Schema::hasColumn('stock_count_items', 'adjusted_at')) {
                $table->dropColumn('adjusted_at');
            }
        });
    }
};
