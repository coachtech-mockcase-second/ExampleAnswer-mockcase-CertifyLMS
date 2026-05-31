<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 単位の読了マーク。1 Enrollment × 1 Section の最大 1 行(UNIQUE)、再マークは UPDATE。
 * Enrollment / Section の物理削除は restrictOnDelete で抑止。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_progresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->restrictOnDelete();
            $table->foreignUlid('section_id')
                ->constrained('sections')
                ->restrictOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['enrollment_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_progresses');
    }
};
