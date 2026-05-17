<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * UserStatus enum を ('invited', 'in_progress', 'graduated', 'withdrawn') の 4 値最終形に確定する。
 *
 * SQLite では DB レベルの enum 制約を持たないため No-Op。MySQL 環境では以下に相当する処理が必要:
 * DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('invited','in_progress','graduated','withdrawn') NOT NULL DEFAULT 'invited'");
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite: No-op (string カラム、enum 制約なし)
    }

    public function down(): void
    {
        // No-op (SQLite)
    }
};
