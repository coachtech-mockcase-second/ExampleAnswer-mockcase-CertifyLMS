<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrollment 状態遷移の監査ログ。SoftDelete 非採用(履歴は不可逆)、INSERT only。
 * from_status / to_status で遷移を表現するため、イベント分類 event_type カラムは持たない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_status_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignUlid('changed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('changed_at');
            $table->string('changed_reason', 200)->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_status_logs');
    }
};
