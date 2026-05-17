<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 既存 'active' ステータスのユーザーと UserStatusLog を 'in_progress' に移行する。
 *
 * UserStatus enum で `Active` が `InProgress` にリネームされたため、DB データを新しい値に揃える。
 * このデータ移行は不可逆 (どのレコードが移行前から `in_progress` だったか区別できない) のため、
 * rollback は許可せず例外を投げる。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('status', 'active')
            ->update(['status' => 'in_progress']);

        DB::table('user_status_logs')
            ->where('status', 'active')
            ->update(['status' => 'in_progress']);
    }

    public function down(): void
    {
        throw new \RuntimeException(
            '不可逆データ移行のため rollback できません。移行前の状態に戻すには DB バックアップから復元してください。',
        );
    }
};
