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
        Schema::table('generations', function (Blueprint $table) {
            $table->unsignedBigInteger('api_key_id')->nullable()->after('user_id');
            $table->foreign('api_key_id')->references('id')->on('api_keys')->onDelete('set null');
            $table->index('api_key_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropForeign(['api_key_id']);
            $table->dropIndex(['api_key_id']);
            $table->dropColumn('api_key_id');
        });
    }
};
