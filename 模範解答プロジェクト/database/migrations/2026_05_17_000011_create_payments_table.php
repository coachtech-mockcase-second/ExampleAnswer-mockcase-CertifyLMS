<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignUlid('meeting_quota_plan_id')->constrained('meeting_quota_plans')->restrictOnDelete();
            $table->string('stripe_payment_intent_id', 255)->nullable()->unique();
            $table->string('stripe_checkout_session_id', 255)->unique();
            $table->unsignedInteger('amount');
            $table->unsignedSmallInteger('quantity');
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index(['status', 'paid_at']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
