<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('instagram')->nullable()->after('cnpj');
            $table->text('description')->nullable()->after('instagram');
            $table->timestamp('onboarding_completed_at')->nullable()->after('active');
        });

        DB::table('companies')
            ->whereNull('onboarding_completed_at')
            ->update([
                'onboarding_completed_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['instagram', 'description', 'onboarding_completed_at']);
        });
    }
};
