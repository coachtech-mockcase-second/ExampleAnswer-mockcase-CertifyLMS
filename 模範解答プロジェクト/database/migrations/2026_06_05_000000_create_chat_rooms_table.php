<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講登録(Enrollment)ごとに 1 件作成されるグループ chat ルーム。
 *
 * status カラムは持たない(ChatRoom は状態遷移ロジックを持たない)。
 * last_message_at は denormalize で、最新メッセージ INSERT 時に ChatMessage::booted() で UPDATE される。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')
                ->unique()
                ->constrained('enrollments')
                ->restrictOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('last_message_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};
