<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'is_internal')) {
                $table->boolean('is_internal')->default(false)->after('active');
            }
        });

        if (Schema::hasColumn('users', 'global_role') && Schema::hasColumn('users', 'company_id')) {
            $internalCompanyIds = DB::table('users')
                ->where('global_role', 'super_admin')
                ->whereNotNull('company_id')
                ->pluck('company_id')
                ->filter()
                ->unique()
                ->values();

            if ($internalCompanyIds->isNotEmpty()) {
                DB::table('companies')
                    ->whereIn('id', $internalCompanyIds)
                    ->update(['is_internal' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'is_internal')) {
                $table->dropColumn('is_internal');
            }
        });
    }
};
