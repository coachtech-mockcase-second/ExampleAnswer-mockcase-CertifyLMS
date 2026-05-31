<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users テーブルに default_enrollment_id カラムを追加する。
 *
 * 受講生のデフォルト資格(サイドバー / 教材 / 模試 / 面談予約画面で自動解決される受講登録)を保持する。
 * Enrollment の物理削除時に自動 NULL リセットさせるため ON DELETE SET NULL。
 * SoftDelete 時の整合は DefaultEnrollmentService::clearIfInvalid で対処する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('default_enrollment_id')
                ->nullable()
                ->after('meeting_url')
                ->constrained('enrollments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_enrollment_id');
        });
    }
};
