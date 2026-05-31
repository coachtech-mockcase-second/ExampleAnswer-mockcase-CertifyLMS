<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 管理者お知らせ本体テーブルの名称を `admin_announcements` から `announcements` に変更する。
 *
 * Model / Policy / UseCase namespace を Entity 単位命名へ統一する規約変更に伴う追従。
 * 既存 index (`admin_announcements_target_dispatched_idx`) は内部名のまま残るが、機能上の影響はない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('admin_announcements', 'announcements');
    }

    public function down(): void
    {
        Schema::rename('announcements', 'admin_announcements');
    }
};
