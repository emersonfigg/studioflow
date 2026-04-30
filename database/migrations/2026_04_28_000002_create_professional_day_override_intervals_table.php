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
        Schema::create('professional_day_override_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_day_override_id')
                ->constrained('professional_day_overrides')
                ->cascadeOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['professional_day_override_id', 'start_time'], 'pdoi_override_start');
        });

        DB::table('professional_day_overrides')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('id')
            ->chunkById(100, function ($overrides): void {
                $timestamp = now();
                $rows = [];

                foreach ($overrides as $override) {
                    $rows[] = [
                        'professional_day_override_id' => $override->id,
                        'start_time' => $override->start_time,
                        'end_time' => $override->end_time,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($rows !== []) {
                    DB::table('professional_day_override_intervals')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_day_override_intervals');
    }
};
