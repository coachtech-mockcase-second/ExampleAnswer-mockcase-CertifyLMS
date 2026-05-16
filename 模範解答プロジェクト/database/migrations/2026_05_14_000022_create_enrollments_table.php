<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [[enrollment]] Feature の正式実装に先立つ stub。
 * [[certification-management]] が必要とする最小スキーマのみ定義する。
 * Enrollment Feature 実装時、追加カラムは別 migration（`add_*_to_enrollments_table`）で足す。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->restrictOnDelete();
            $table->string('status', 20)->default('learning');
            $table->date('exam_date')->nullable();
            $table->string('current_term', 30)->nullable();
            $table->timestamp('completion_requested_at')->nullable();
            $table->timestamp('passed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('certification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
