<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->restrictOnDelete();
            $table->foreignUlid('section_id')
                ->nullable()
                ->constrained('sections')
                ->nullOnDelete();
            $table->foreignUlid('category_id')
                ->constrained('question_categories')
                ->restrictOnDelete();
            $table->text('body');
            $table->text('explanation')->nullable();
            $table->string('difficulty', 10)->default('medium');
            $table->unsignedInteger('order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['certification_id', 'status']);
            $table->index(['certification_id', 'difficulty']);
            $table->index('section_id');
            $table->index('category_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
