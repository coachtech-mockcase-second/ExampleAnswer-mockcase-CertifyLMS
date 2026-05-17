<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講生が Enrollment 単位で立てる個人目標。
 * 受講生本人のみ CRUD 可、coach / admin は閲覧専用。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('description', 1000)->nullable();
            $table->date('target_date')->nullable();
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['enrollment_id', 'achieved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_goals');
    }
};
