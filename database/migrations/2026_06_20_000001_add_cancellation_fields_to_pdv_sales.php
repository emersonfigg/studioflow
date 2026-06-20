<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('service_orders', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_orders', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_by');
            }
            if (! Schema::hasColumn('service_orders', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'status')) {
                $table->string('status', 32)->default('completed')->after('service_order_id')->index();
            }
            if (! Schema::hasColumn('payments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('payments', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_by');
            }
        });

        Schema::table('product_sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_sales', 'status')) {
                $table->string('status', 32)->default('completed')->after('service_order_id')->index();
            }
            if (! Schema::hasColumn('product_sales', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('product_sales', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('product_sales', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_by');
            }
            if (! Schema::hasColumn('product_sales', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('customer_memberships', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_memberships', 'service_order_id')) {
                $table->foreignId('service_order_id')
                    ->nullable()
                    ->after('membership_plan_id')
                    ->constrained('service_orders')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_memberships', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_memberships', 'service_order_id')) {
                $table->dropConstrainedForeignId('service_order_id');
            }
        });

        Schema::table('product_sales', function (Blueprint $table): void {
            if (Schema::hasColumn('product_sales', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            foreach (['cancel_reason', 'cancelled_at', 'status'] as $column) {
                if (Schema::hasColumn('product_sales', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('product_sales', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            foreach (['cancel_reason', 'cancelled_at', 'status'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('service_orders', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            foreach (['cancel_reason', 'cancelled_at'] as $column) {
                if (Schema::hasColumn('service_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('service_orders', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
