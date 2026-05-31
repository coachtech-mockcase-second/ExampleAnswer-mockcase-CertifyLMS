<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel 標準の Database Notification チャネルで使用するテーブル。
 * `Illuminate\Notifications\Notifiable` トレイトの `notify()` から database channel 経由で書き込まれる。
 *
 * id は ULID で生成する (時系列ソートで TopBar / 通知ポップオーバーの並び替えコストを下げる)。
 * ulidMorphs("notifiable") を採用するのは notifiable_id を ULID で受ける User と morph するため
 * (`morphs` のデフォルト UNSIGNED BIGINT では ULID(26 文字) が「Data truncated」になり配信が失敗する)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type');
            $table->ulidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_idx');
            $table->index('created_at', 'notifications_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
