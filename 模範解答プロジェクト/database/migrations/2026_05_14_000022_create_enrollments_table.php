<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講登録(Enrollment)の中心テーブル。
 *
 * 1 受講生 × 1 資格 = 1 行。assigned_coach_id は持たない(担当コーチは資格 × N コーチ N:N、
 * certification_coach_assignments で資格経由で割当)。修了は受講生自己完結の即時 passed 遷移。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->restrictOnDelete();
            $table->date('exam_date')->nullable();
            $table->string('status', 20)->default('learning');
            $table->string('current_term', 30)->default('basic_learning');
            $table->timestamp('passed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'certification_id']);
            $table->index(['user_id', 'status']);
            $table->index(['certification_id', 'status']);
            // Schedule Command(enrollments:fail-expired)の高速抽出用
            $table->index(['status', 'exam_date']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
