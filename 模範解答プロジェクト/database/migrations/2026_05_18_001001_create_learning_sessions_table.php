<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 詳細ページ滞在時間を記録する学習セッション。
 *
 * user_id は enrollment.user_id 経由で取得可能だが denormalize して保持する。
 * StreakService (DISTINCT DATE(started_at) WHERE user_id) や dashboard の全資格横断集計、
 * Schedule Command の残骸 open 抽出が user_id 単独で完結し、enrollment JOIN を回避できる。
 *
 * auto_closed = true は「別 Section 表示時の自動切替 close」「Schedule Command 強制 close」を識別するためのフラグ。
 * 集計上は明示 close と auto close を区別する場面はないが、将来の分析(集中度 / 中断率)用に保持する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->restrictOnDelete();
            $table->foreignUlid('section_id')
                ->constrained('sections')
                ->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('auto_closed')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'started_at']);
            $table->index(['enrollment_id', 'started_at']);
            $table->index(['user_id', 'ended_at']);
            $table->index(['enrollment_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_sessions');
    }
};
