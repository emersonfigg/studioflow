<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'recommended_return_days')) {
                $table->unsignedSmallInteger('recommended_return_days')->nullable()->after('active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'recommended_return_days')) {
                $table->dropColumn('recommended_return_days');
            }
        });
    }
};
