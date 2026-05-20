<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講生 × AI の相談会話を表すテーブル。
 *
 * - user_id: 会話オーナー(受講生)。User 削除時に cascade
 * - enrollment_id / section_id: nullable。教材コンテキスト(Section)に紐付く会話は両方 non-null になる
 * - last_message_at: 一覧の降順並び替え + フローティングウィジェットの既存会話再開判定に使用
 * - SoftDelete: 受講生の削除操作で論理削除。メッセージは cascade で残存しない
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUlid('enrollment_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignUlid('section_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('title', 100);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'last_message_at']);
            $table->index(['user_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_conversations');
    }
};
