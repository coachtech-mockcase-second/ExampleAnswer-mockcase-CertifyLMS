<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コーチが Enrollment 単位で受講生の観察を時系列で残すメモ。受講生本人には閲覧不可。
 * coach は自分の作成分のみ編集 / 削除可、admin は越境可。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->cascadeOnDelete();
            $table->foreignUlid('coach_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('body', 2000);
            $table->timestamps();

            $table->index(['enrollment_id', 'created_at']);
            $table->index('coach_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_notes');
    }
};
