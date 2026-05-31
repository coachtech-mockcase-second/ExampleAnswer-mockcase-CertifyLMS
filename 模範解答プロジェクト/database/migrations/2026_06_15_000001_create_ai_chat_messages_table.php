<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI 相談会話の個別メッセージを表すテーブル。
 *
 * - role: 'user' / 'assistant' (OpenAI 形式)
 * - status: assistant role のみ意味を持つ ('pending' / 'completed' / 'error')。user は INSERT 直後に completed 固定
 * - model / input_tokens / output_tokens / response_time_ms: assistant の応答メタ
 * - error_detail: assistant エラー時の内部ログ。受講生には汎用文言を表示する
 *
 * 親 ai_chat_conversation が物理削除されるとメッセージも cascade で物理削除される。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ai_chat_conversation_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->string('status');
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamps();

            $table->index(['ai_chat_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
