<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('global_role')->nullable()->after('role');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('active')->default(true)->after('logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('global_role');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('active');
        });
    }
};
