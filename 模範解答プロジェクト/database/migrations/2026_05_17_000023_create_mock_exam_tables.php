<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrollment Feature が必要とする mock_exams / mock_exam_sessions の最小スキーマ。
 *
 * CompletionEligibilityService が「公開模試件数 == 合格セッション件数」を判定するため、
 * 模試マスタ(is_published)と受験セッション(enrollment_id / pass / mock_exam_id) の最小カラムのみ定義する。
 * 設問・解答・採点まわりの完全実装は mock-exam Feature 側で別 migration で拡張する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_exams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->restrictOnDelete();
            $table->string('title', 255);
            $table->unsignedSmallInteger('passing_score')->default(60);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['certification_id', 'is_published']);
        });

        Schema::create('mock_exam_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->restrictOnDelete();
            $table->foreignUlid('mock_exam_id')
                ->constrained('mock_exams')
                ->restrictOnDelete();
            $table->string('status', 20)->default('not_started');
            $table->boolean('pass')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['enrollment_id', 'status']);
            $table->index(['enrollment_id', 'mock_exam_id', 'pass']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_exam_sessions');
        Schema::dropIfExists('mock_exams');
    }
};
