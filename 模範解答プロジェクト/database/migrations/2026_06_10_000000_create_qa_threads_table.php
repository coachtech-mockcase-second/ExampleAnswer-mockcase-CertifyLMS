<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講生 / コーチが資格別に技術質問を公開で投稿する Q&A 掲示板のスレッドテーブル。
 *
 * - 解決状態は `status` Enum (open / resolved) と `resolved_at` の同時更新で表現
 * - 削除は物理削除 (モデレーション削除も投稿者削除も履歴を残さない)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->restrictOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status')->default('open');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['certification_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_threads');
    }
};
