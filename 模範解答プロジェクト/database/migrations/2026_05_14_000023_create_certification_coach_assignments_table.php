<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_coach_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->cascadeOnDelete();
            $table->foreignUlid('coach_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('assigned_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique(['certification_id', 'coach_user_id'], 'cert_coach_assign_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_coach_assignments');
    }
};
