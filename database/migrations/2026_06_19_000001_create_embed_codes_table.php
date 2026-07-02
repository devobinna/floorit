<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('embed_codes')) {
            return;
        }

        Schema::create('embed_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->json('allowed_domains')->nullable();
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embed_codes');
    }
};
