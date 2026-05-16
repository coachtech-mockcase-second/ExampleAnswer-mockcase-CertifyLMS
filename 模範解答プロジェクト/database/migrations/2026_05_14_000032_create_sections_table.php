<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('chapter_id')
                ->constrained('chapters')
                ->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->longText('body');
            $table->unsignedInteger('order')->default(1);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['chapter_id', 'order']);
            $table->index(['chapter_id', 'status']);
            $table->index('title');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
