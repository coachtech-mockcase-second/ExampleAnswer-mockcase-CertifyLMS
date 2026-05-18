<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ChatRoom 参加者の中間テーブル。
 *
 * 1 ChatRoom = 受講生本人 + 当該資格の担当コーチ集合全員の ChatMember レコードを持つ。
 * last_read_at は個人別に保持し、未読バッジ集計と「個人別既読」の根拠とする
 * (あるコーチが既読を付けても、他コーチの last_read_at には影響しない)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('chat_room_id')
                ->constrained('chat_rooms')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['chat_room_id', 'user_id']);
            $table->index(['user_id', 'last_read_at']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_members');
    }
};
