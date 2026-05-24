<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 面談メモテーブル(1 面談 : 1 メモ)。
 *
 * 記述者は常に担当コーチ(`meetings.coach_id` で一意に決まるため author カラムは持たない)。
 * 受講生は閲覧のみ可能で、`reserved` 段階の事前メモは内部用としてコーチからのみ参照する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_memos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->unique()->constrained('meetings')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_memos');
    }
};
