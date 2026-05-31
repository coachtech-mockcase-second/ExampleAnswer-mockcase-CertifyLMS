<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 模試 Feature の永続化スキーマ。
 *
 * MockExam マスタ + MockExamQuestion(子リソース) + MockExamQuestionOption(孫リソース) と、
 * 受験セッション(MockExamSession) + 各問の解答ログ(MockExamAnswer) の 5 テーブルを 1 migration で生成する。
 * 時間制限機能は持たない(受講生は時間制限なしで自分のペースで解答 + 明示提出)。
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
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->unsignedTinyInteger('passing_score');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignUlid('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('updated_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['certification_id', 'is_published', 'order']);
        });

        Schema::create('mock_exam_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mock_exam_id')
                ->constrained('mock_exams')
                ->cascadeOnDelete();
            $table->foreignUlid('category_id')
                ->constrained('question_categories')
                ->restrictOnDelete();
            $table->text('body');
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index(['mock_exam_id', 'order']);
        });

        Schema::create('mock_exam_question_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mock_exam_question_id')
                ->constrained('mock_exam_questions')
                ->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_correct');
            $table->unsignedSmallInteger('order');
            $table->timestamps();

            $table->index(['mock_exam_question_id', 'order']);
        });

        Schema::create('mock_exam_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mock_exam_id')
                ->constrained('mock_exams')
                ->restrictOnDelete();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->restrictOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('status', 20)->default('not_started');
            $table->json('generated_question_ids');
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedTinyInteger('passing_score_snapshot');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->unsignedSmallInteger('total_correct')->nullable();
            $table->decimal('score_percentage', 5, 2)->nullable();
            $table->boolean('pass')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'status']);
            $table->index(['mock_exam_id', 'pass']);
            $table->index(['user_id', 'graded_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('mock_exam_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mock_exam_session_id')
                ->constrained('mock_exam_sessions')
                ->cascadeOnDelete();
            $table->foreignUlid('mock_exam_question_id')
                ->constrained('mock_exam_questions')
                ->restrictOnDelete();
            $table->foreignUlid('selected_option_id')
                ->nullable()
                ->constrained('mock_exam_question_options')
                ->nullOnDelete();
            $table->string('selected_option_body', 2000);
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->unique(
                ['mock_exam_session_id', 'mock_exam_question_id'],
                'mock_exam_answers_session_question_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_exam_answers');
        Schema::dropIfExists('mock_exam_sessions');
        Schema::dropIfExists('mock_exam_question_options');
        Schema::dropIfExists('mock_exam_questions');
        Schema::dropIfExists('mock_exams');
    }
};
