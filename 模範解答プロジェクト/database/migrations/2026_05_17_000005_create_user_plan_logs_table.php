<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_plan_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('event_type', 20);
            $table->timestamp('plan_started_at');
            $table->timestamp('plan_expires_at');
            $table->unsignedSmallInteger('meeting_quota_initial');
            $table->foreignUlid('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_reason', 200)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_plan_logs');
    }
};
