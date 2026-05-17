<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_quota_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('meeting_count');
            $table->unsignedInteger('price');
            $table->string('stripe_price_id', 255)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUlid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_quota_plans');
    }
};
