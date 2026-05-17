<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->foreignUlid('category_id')
                ->constrained('certification_categories')
                ->restrictOnDelete();
            $table->string('difficulty', 20);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignUlid('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('updated_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
