<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users テーブルに meeting_url カラムを追加する。
 *
 * コーチがオンボーディング時に必須入力する固定面談 URL(Google Meet / Zoom 等の招待リンク)を保持する。
 * 受講生 / 管理者ロールでは NULL のまま運用する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('meeting_url', 500)->nullable()->after('max_meetings');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('meeting_url');
        });
    }
};
