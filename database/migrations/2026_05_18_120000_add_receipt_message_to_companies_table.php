<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'receipt_message')) {
                $column = $table->string('receipt_message', 120)->nullable();

                if (Schema::hasColumn('companies', 'custom_footer_text')) {
                    $column->after('custom_footer_text');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'receipt_message')) {
                $table->dropColumn('receipt_message');
            }
        });
    }
};
