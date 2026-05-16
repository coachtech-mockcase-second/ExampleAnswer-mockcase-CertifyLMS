<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug', 60)->unique();
            $table->string('name', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_categories');
    }
};
