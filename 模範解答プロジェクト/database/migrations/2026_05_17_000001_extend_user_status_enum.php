<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * UserStatus enum 拡張(暫定 5 値): ('invited', 'active', 'withdrawn') に 'in_progress' / 'graduated' を追加。
 *
 * 本 PJ は SQLite を使用しており、`users.status` は string カラムで DB レベルの enum 制約を持たないため、
 * MySQL 環境を意識した「拡張ポイント」を残す No-Op 同等の Migration として扱う
 * (コード側 Enum 拡張は app/Enums/UserStatus.php で実施)。
 * データ移行は本 Migration の次ステップ、最終 4 値化はさらにその次ステップで行う。
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite: status カラムは string、enum 制約なしのため DDL 不要。
        // MySQL 環境では以下に相当する処理が必要:
        // DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('invited','active','in_progress','graduated','withdrawn') NOT NULL DEFAULT 'invited'");
    }

    public function down(): void
    {
        // No-op (SQLite)
    }
};
