<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrollment 単位の学習時間目標。1 Enrollment あたり最大 1 行(UNIQUE)、未設定は行なしで表現する。
 * 取消は SoftDelete、再設定は restore + UPDATE。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_hour_targets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('target_total_hours');
            $table->timestamps();

            $table->unique('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_hour_targets');
    }
};
