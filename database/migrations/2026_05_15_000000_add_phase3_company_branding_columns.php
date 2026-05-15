<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'favicon_path')) {
                $table->string('favicon_path')->nullable()->after('logo');
            }
            if (! Schema::hasColumn('companies', 'cover_image_path')) {
                $table->string('cover_image_path')->nullable()->after('favicon_path');
            }
            if (! Schema::hasColumn('companies', 'primary_color')) {
                $table->string('primary_color', 7)->nullable()->after('cover_image_path');
            }
            if (! Schema::hasColumn('companies', 'secondary_color')) {
                $table->string('secondary_color', 7)->nullable()->after('primary_color');
            }
            if (! Schema::hasColumn('companies', 'accent_color')) {
                $table->string('accent_color', 7)->nullable()->after('secondary_color');
            }
            if (! Schema::hasColumn('companies', 'public_headline')) {
                $table->string('public_headline')->nullable()->after('accent_color');
            }
            if (! Schema::hasColumn('companies', 'public_subheadline')) {
                $table->string('public_subheadline', 500)->nullable()->after('public_headline');
            }
            if (! Schema::hasColumn('companies', 'welcome_message')) {
                $table->text('welcome_message')->nullable()->after('public_subheadline');
            }
            if (! Schema::hasColumn('companies', 'custom_footer_text')) {
                $table->string('custom_footer_text', 500)->nullable()->after('welcome_message');
            }
            if (! Schema::hasColumn('companies', 'brand_enabled')) {
                $table->boolean('brand_enabled')->default(true)->after('custom_footer_text');
            }
        });
    }

    public function down(): void
    {
        $cols = array_values(array_filter([
            'favicon_path',
            'cover_image_path',
            'primary_color',
            'secondary_color',
            'accent_color',
            'public_headline',
            'public_subheadline',
            'welcome_message',
            'custom_footer_text',
            'brand_enabled',
        ], fn (string $col): bool => Schema::hasColumn('companies', $col)));

        if ($cols !== []) {
            Schema::table('companies', function (Blueprint $table) use ($cols): void {
                $table->dropColumn($cols);
            });
        }
    }
};
