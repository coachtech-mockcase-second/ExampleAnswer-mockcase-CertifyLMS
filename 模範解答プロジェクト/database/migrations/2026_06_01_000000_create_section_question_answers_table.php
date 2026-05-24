<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講生の個別解答ログ。SectionQuestion を 1 問解くたびに 1 行追加される append-only テーブル。
 *
 * selected_option_id は SectionQuestionOption の物理削除に追従して NULL になるため、
 * 履歴可読性は selected_option_body スナップショットで担保する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_question_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('section_question_id')
                ->constrained('section_questions')
                ->restrictOnDelete();
            $table->foreignUlid('selected_option_id')
                ->nullable()
                ->constrained('section_question_options')
                ->nullOnDelete();
            $table->string('selected_option_body', 2000);
            $table->boolean('is_correct');
            $table->string('source', 20);
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->index(['user_id', 'answered_at']);
            $table->index(['user_id', 'section_question_id']);
            $table->index(['section_question_id', 'is_correct']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_question_answers');
    }
};
