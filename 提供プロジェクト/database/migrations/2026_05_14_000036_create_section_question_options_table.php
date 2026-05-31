<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_question_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('section_question_id')
                ->constrained('section_questions')
                ->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index(['section_question_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_question_options');
    }
};
