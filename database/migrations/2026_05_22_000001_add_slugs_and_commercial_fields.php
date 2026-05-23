<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
        });

        DB::table('companies')
            ->select(['id', 'name', 'slug'])
            ->orderBy('id')
            ->chunkById(100, function ($companies): void {
                foreach ($companies as $company) {
                    if (filled($company->slug)) {
                        continue;
                    }

                    $base = Str::slug((string) $company->name) ?: 'empresa-'.$company->id;
                    $slug = $base;
                    $suffix = 2;

                    while (DB::table('companies')->where('slug', $slug)->where('id', '!=', $company->id)->exists()) {
                        $slug = $base.'-'.$suffix++;
                    }

                    DB::table('companies')->where('id', $company->id)->update(['slug' => $slug]);
                }
            });

        Schema::table('companies', function (Blueprint $table): void {
            $table->unique('slug');
        });

        Schema::table('membership_plan_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('membership_plan_services', 'special_duration_minutes')) {
                $table->unsignedSmallInteger('special_duration_minutes')->nullable()->after('included');
            }
        });

        Schema::table('customer_memberships', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_memberships', 'renews_at')) {
                $table->date('renews_at')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('customer_memberships', 'accepted_terms_ip')) {
                $table->string('accepted_terms_ip', 45)->nullable()->after('accepted_terms_at');
            }
            if (! Schema::hasColumn('customer_memberships', 'accepted_terms_user_agent')) {
                $table->text('accepted_terms_user_agent')->nullable()->after('accepted_terms_ip');
            }
        });

        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'price_mode')) {
                $table->string('price_mode', 20)->default('fixed')->after('price');
            }
            if (! Schema::hasColumn('services', 'allow_pdv_price_edit')) {
                $table->boolean('allow_pdv_price_edit')->default(false)->after('price_mode');
            }
        });

        Schema::table('service_order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_order_items', 'original_unit_price')) {
                $table->decimal('original_unit_price', 10, 2)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('service_order_items', 'price_adjustment_amount')) {
                $table->decimal('price_adjustment_amount', 10, 2)->nullable()->after('original_unit_price');
            }
            if (! Schema::hasColumn('service_order_items', 'price_adjustment_reason')) {
                $table->string('price_adjustment_reason')->nullable()->after('price_adjustment_amount');
            }
            if (! Schema::hasColumn('service_order_items', 'price_adjusted_by')) {
                $table->foreignId('price_adjusted_by')->nullable()->after('price_adjustment_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_order_items', 'price_adjusted_at')) {
                $table->timestamp('price_adjusted_at')->nullable()->after('price_adjusted_by');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'category')) {
                $table->string('category')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'category')) {
                $table->dropColumn('category');
            }
        });

        Schema::table('service_order_items', function (Blueprint $table): void {
            foreach (['price_adjusted_at', 'price_adjusted_by', 'price_adjustment_reason', 'price_adjustment_amount', 'original_unit_price'] as $column) {
                if (Schema::hasColumn('service_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('services', function (Blueprint $table): void {
            foreach (['allow_pdv_price_edit', 'price_mode'] as $column) {
                if (Schema::hasColumn('services', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('customer_memberships', function (Blueprint $table): void {
            foreach (['accepted_terms_user_agent', 'accepted_terms_ip', 'renews_at'] as $column) {
                if (Schema::hasColumn('customer_memberships', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('membership_plan_services', function (Blueprint $table): void {
            if (Schema::hasColumn('membership_plan_services', 'special_duration_minutes')) {
                $table->dropColumn('special_duration_minutes');
            }
        });

        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });
    }
};
