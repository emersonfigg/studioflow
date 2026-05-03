<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            if (! Schema::hasColumn('clients', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('clients', 'google_id')) {
                $table->string('google_id')->nullable()->after('email');
            }

            if (! Schema::hasColumn('clients', 'avatar')) {
                $table->string('avatar')->nullable()->after('google_id');
            }

            if (! Schema::hasColumn('clients', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('avatar');
            }
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->index(['company_id', 'email'], 'clients_company_email_index');
            $table->unique(['company_id', 'google_id'], 'clients_company_google_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique('clients_company_google_unique');
            $table->dropIndex('clients_company_email_index');
        });

        Schema::table('clients', function (Blueprint $table): void {
            foreach (['email_verified_at', 'avatar', 'google_id', 'email'] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
