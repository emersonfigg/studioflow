<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'commission_type')) {
                $table->string('commission_type', 20)->nullable()->after('active');
            }
            if (! Schema::hasColumn('products', 'commission_value')) {
                $table->decimal('commission_value', 10, 2)->nullable()->after('commission_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'commission_value')) {
                $table->dropColumn('commission_value');
            }
            if (Schema::hasColumn('products', 'commission_type')) {
                $table->dropColumn('commission_type');
            }
        });
    }
};
