<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel 標準の Database Notification チャネルで使用するテーブル。
 * `Illuminate\Notifications\Notifiable` トレイトの `notify()` から database channel 経由で書き込まれる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            // ULID 主キーの User と morph するため `ulidMorphs` を使う
            // (`morphs` のデフォルト UNSIGNED BIGINT では ULID(26 文字)が
            // 「Data truncated」になり notification の DB 配信が失敗する)
            $table->ulidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
