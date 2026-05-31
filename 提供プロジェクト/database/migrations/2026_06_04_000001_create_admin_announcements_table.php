<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理者から受講生に配信するお知らせ本体を保持するテーブル。
 *
 * 1 行 = 1 配信。配信時に対象 User Collection を展開して `notifications` 行を発火する。
 * target_type で対象集合 (全受講中の受講生 / 指定資格の受講生 / 指定ユーザー) を切り替える。
 * 再配信 / 編集 / 取消は提供しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('target_type');
            $table->foreignUlid('target_certification_id')->nullable()->constrained('certifications')->nullOnDelete();
            $table->foreignUlid('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('dispatched_count')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'dispatched_at'], 'admin_announcements_target_dispatched_idx');
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_announcements');
    }
};
