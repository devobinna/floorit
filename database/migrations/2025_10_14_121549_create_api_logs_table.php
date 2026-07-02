<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->string('endpoint');
            $table->string('method', 10); // GET, POST, PUT, DELETE
            $table->json('request_data')->nullable();
            $table->unsignedSmallInteger('response_code');
            $table->unsignedInteger('response_time'); // milliseconds
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');
            
            $table->index(['user_id', 'created_at']);
            $table->index('api_key_id');
            $table->index(['endpoint', 'method']);
            $table->index('response_code');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
