<?php

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
            $table->foreignUlid('changed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status');
            $table->timestamp('changed_at');
            $table->string('changed_reason', 200)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('changed_by_user_id');
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_status_logs');
    }
};
