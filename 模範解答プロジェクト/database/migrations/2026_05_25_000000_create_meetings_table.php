<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1on1 面談予約テーブル。
 *
 * 受講生が時刻スロットを選んだ瞬間、過去 30 日の実施数が最少の担当コーチを自動割当し
 * `status=reserved` で即時確定する(コーチによる承認フローはない)。
 * scheduled_at は開始時刻のみ保持し、終了時刻は常に `scheduled_at + 60 分` とする運用(NFR で 60 分固定)。
 *
 * (coach_id, scheduled_at) UNIQUE で同コーチ × 同時刻の二重予約を DB レベルで禁止し、
 * Action 内の race condition は INSERT 失敗を `MeetingNoAvailableCoachException` に変換することで吸収する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignUlid('coach_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('status', 20);
            $table->text('topic');
            $table->foreignUlid('canceled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('canceled_at')->nullable();
            $table->string('meeting_url_snapshot', 500)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignUlid('meeting_quota_transaction_id')
                ->nullable()
                ->constrained('meeting_quota_transactions')
                ->nullOnDelete();
            $table->timestamps();

            // 同コーチ × 同時刻の二重予約禁止(status 問わず、race condition の最終防衛線)
            $table->unique(['coach_id', 'scheduled_at']);

            // 受講生別履歴一覧 / 自動完了 Schedule Command 高速化のための補助 INDEX
            $table->index(['student_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
