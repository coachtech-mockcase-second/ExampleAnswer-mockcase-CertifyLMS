<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コーチの Google Calendar OAuth 認証情報テーブル。
 *
 * 1 コーチ : 1 認証情報を強制(UNIQUE coach_id)。`access_token` は短寿命なので `refresh_token` で
 * 自動更新する。`calendar_id` はカレンダー識別子(プライマリは `primary`)で、コーチが将来別カレンダーを
 * 指定する余地を残す。token は教材としての可視性を優先しプレーンテキスト保存(本番運用では encrypt 推奨)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_google_credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('coach_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('access_token', 2048);
            $table->string('refresh_token', 512);
            $table->string('calendar_id', 255)->default('primary');
            $table->dateTime('connected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_google_credentials');
    }
};
