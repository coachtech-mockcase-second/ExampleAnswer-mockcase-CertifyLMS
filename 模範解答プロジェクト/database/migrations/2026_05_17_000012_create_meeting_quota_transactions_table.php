<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_quota_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->integer('amount');
            // related_meeting_id: meetings テーブル(mentoring Feature 所有)未作成時の依存回避のため FK 制約は別 Migration で追加する
            $table->ulid('related_meeting_id')->nullable();
            $table->foreignUlid('related_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignUlid('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index('related_meeting_id');
            $table->index('related_payment_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_quota_transactions');
    }
};
