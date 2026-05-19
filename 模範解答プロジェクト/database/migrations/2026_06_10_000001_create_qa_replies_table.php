<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 質問スレッドへの回答を表す中間テーブル。
 *
 * 親スレッド削除時に cascade させず、SoftDelete されたスレッド配下の回答は閲覧経路から除外するのみとし
 * 履歴は維持する (回答件数チェックは withTrashed() で行う)。
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
            $table->softDeletes();

            $table->index(['qa_thread_id', 'created_at']);
            $table->index('user_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_replies');
    }
};
