<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_payment_integrations', function (Blueprint $table) {
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->timestamp('expires_at')->nullable()->after('metadata');
            $table->timestamp('connected_at')->nullable()->after('expires_at');
            $table->string('status', 32)->nullable()->after('connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('company_payment_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'refresh_token',
                'expires_at',
                'connected_at',
                'status',
            ]);
        });
    }
};
