<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_images', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('section_id')
                ->constrained('sections')
                ->restrictOnDelete();
            $table->string('path', 255)->unique();
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['section_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_images');
    }
};
