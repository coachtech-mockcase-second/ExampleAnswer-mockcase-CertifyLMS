<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講生 × SectionQuestion ごとの累計サマリ。解答送信のたびに UPSERT され、
 * attempt_count / correct_count / 最終正誤 / 最終解答日時を保持する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_question_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('section_question_id')
                ->constrained('section_questions')
                ->restrictOnDelete();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->boolean('last_is_correct')->default(false);
            $table->timestamp('last_answered_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'section_question_id']);
            $table->index(['user_id', 'last_answered_at']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_question_attempts');
    }
};
