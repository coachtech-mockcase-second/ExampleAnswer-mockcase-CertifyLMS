<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 担当コーチの面談可能時間枠テーブル(曜日 × 開始時刻 × 終了時刻の繰り返し枠)。
 *
 * 1 コーチ × 1 曜日に複数枠を許容する(例: 月曜 09:00-12:00 と月曜 14:00-17:00 を両方登録可)。
 * 同日範囲のみ(`start_time < end_time`)で日跨ぎ枠は許容しない(アプリケーション層 FormRequest で保証)。
 * 「この月曜だけ休み」など臨時シフトは `is_active` 一時切替か枠の一時削除で運用し、別テーブルは持たない。
 *
 * 編集 UI は設定・プロフィール機能が所有し、本テーブルの読み取りは面談予約機能が所有する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_availabilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('coach_id')->constrained('users')->cascadeOnDelete();
            // 0=日曜, 6=土曜(Carbon::dayOfWeek と整合)
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['coach_id', 'day_of_week']);
            $table->index(['coach_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_availabilities');
    }
};
