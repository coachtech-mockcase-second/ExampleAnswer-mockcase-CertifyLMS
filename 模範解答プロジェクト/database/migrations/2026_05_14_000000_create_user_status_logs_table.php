<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_status_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->foreignUlid('changed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('changed_reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['user_id', 'changed_at']);
            $table->index('changed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_status_logs');
    }
};
