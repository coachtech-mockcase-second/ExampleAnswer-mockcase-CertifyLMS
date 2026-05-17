<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('section_id')
                ->constrained('sections')
                ->cascadeOnDelete();
            $table->foreignUlid('category_id')
                ->constrained('question_categories')
                ->restrictOnDelete();
            $table->text('body');
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['section_id', 'status']);
            $table->index(['section_id', 'order']);
            $table->index('category_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_questions');
    }
};
