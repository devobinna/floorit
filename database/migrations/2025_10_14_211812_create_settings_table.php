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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->string('group')->default('general'); // general, generation, payment, etc.
            $table->text('description')->nullable();
            $table->timestamps();
        });
        
        // Insert default settings
        DB::table('settings')->insert([
            [
                'key' => 'ai_processing_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'generation',
                'description' => 'Enable AI-powered floor replacement using Replicate API (requires API token)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ai_processing_credits',
                'value' => '30',
                'type' => 'integer',
                'group' => 'generation',
                'description' => 'Credits required for AI-powered generation (premium quality)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'basic_processing_credits',
                'value' => '10',
                'type' => 'integer',
                'group' => 'generation',
                'description' => 'Credits required for basic PHP processing (standard quality)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'allow_user_choose_method',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'generation',
                'description' => 'Allow users to choose between basic and AI processing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_processing_method',
                'value' => 'basic',
                'type' => 'string',
                'group' => 'generation',
                'description' => 'Default processing method (basic or ai)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
