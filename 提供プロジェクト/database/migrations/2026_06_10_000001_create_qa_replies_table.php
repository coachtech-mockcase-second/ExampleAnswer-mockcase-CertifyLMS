<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 質問スレッドへの回答を表す中間テーブル。
 *
 * 親スレッド削除時には restrictOnDelete で削除を拒否し、回答 0 件確認後のみ親スレッド削除を許可する。
 * 回答自体の削除も物理削除。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_replies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('qa_thread_id')
                ->constrained('qa_threads')
                ->restrictOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['qa_thread_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_replies');
    }
};
