<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 面談予約に Google Calendar Event ID を関連付けるカラムを追加する。
 *
 * GCal 連携済コーチが受け持つ予約のみ event_id が入る。キャンセル時にこの id を使って
 * GCal の event を削除する。連携なしのコーチ予約や、event 作成失敗時は NULL のままで運用する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('google_event_id', 255)->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('google_event_id');
        });
    }
};
