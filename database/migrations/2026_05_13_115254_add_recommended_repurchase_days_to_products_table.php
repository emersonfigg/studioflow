<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'recommended_repurchase_days')) {
                $table->unsignedSmallInteger('recommended_repurchase_days')->nullable()->after('commission_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'recommended_repurchase_days')) {
                $table->dropColumn('recommended_repurchase_days');
            }
        });
    }
};
