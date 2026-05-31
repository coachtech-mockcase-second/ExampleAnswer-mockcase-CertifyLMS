<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('plan_id')->nullable()->after('status')->constrained('plans')->restrictOnDelete();
            $table->timestamp('plan_started_at')->nullable()->after('plan_id');
            $table->timestamp('plan_expires_at')->nullable()->after('plan_started_at');
            $table->unsignedSmallInteger('max_meetings')->default(0)->after('plan_expires_at');

            $table->index(['status', 'plan_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status', 'plan_expires_at']);
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['plan_started_at', 'plan_expires_at', 'max_meetings']);
        });
    }
};
