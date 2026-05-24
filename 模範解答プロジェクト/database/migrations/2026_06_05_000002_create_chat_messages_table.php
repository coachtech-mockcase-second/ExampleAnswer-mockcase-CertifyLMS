<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ChatRoom に投稿される個別メッセージ。テキスト本文のみ(添付ファイル非対応)。
 *
 * 編集 / 削除エンドポイントは提供しない(append only)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('chat_room_id')
                ->constrained('chat_rooms')
                ->cascadeOnDelete();
            $table->foreignUlid('sender_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['chat_room_id', 'created_at']);
            $table->index('sender_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
