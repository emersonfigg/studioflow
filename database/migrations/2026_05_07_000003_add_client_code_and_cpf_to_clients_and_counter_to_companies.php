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
            if (! Schema::hasColumn('companies', 'client_code_counter')) {
                $table->unsignedInteger('client_code_counter')->default(0)->after('auto_print_receipt');
            }
        });

        Schema::table('clients', function (Blueprint $table): void {
            if (! Schema::hasColumn('clients', 'client_code')) {
                $table->string('client_code', 16)->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('clients', 'cpf')) {
                $table->string('cpf', 20)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('clients', 'cpf_normalized')) {
                $table->string('cpf_normalized', 11)->nullable()->after('cpf');
            }
        });

        $companyIds = DB::table('clients')
            ->select('company_id')
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $counter = 0;

            $clients = DB::table('clients')
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->get(['id', 'cpf']);

            foreach ($clients as $client) {
                $counter++;
                $digits = preg_replace('/\D+/', '', (string) ($client->cpf ?? '')) ?: null;
                $normalized = $digits !== null && strlen($digits) === 11 ? $digits : null;

                DB::table('clients')
                    ->where('id', $client->id)
                    ->update([
                        'client_code' => 'C'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                        'cpf_normalized' => $normalized,
                    ]);
            }

            DB::table('companies')
                ->where('id', $companyId)
                ->update(['client_code_counter' => $counter]);
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->unique(['company_id', 'client_code'], 'clients_company_client_code_unique');
            $table->unique(['company_id', 'cpf_normalized'], 'clients_company_cpf_normalized_unique');
            $table->index(['company_id', 'cpf_normalized'], 'clients_company_cpf_normalized_index');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex('clients_company_cpf_normalized_index');
            $table->dropUnique('clients_company_cpf_normalized_unique');
            $table->dropUnique('clients_company_client_code_unique');
        });

        Schema::table('clients', function (Blueprint $table): void {
            foreach (['cpf_normalized', 'cpf', 'client_code'] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'client_code_counter')) {
                $table->dropColumn('client_code_counter');
            }
        });
    }
};

