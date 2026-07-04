<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('is_publicly_available')->default(true)->after('active');
            $table->boolean('available_for_pos')->default(true)->after('is_publicly_available');
        });

        DB::table('services')->where('active', false)->update([
            'is_publicly_available' => false,
            'available_for_pos' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['is_publicly_available', 'available_for_pos']);
        });
    }
};
