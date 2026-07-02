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
            $table->enum('source', ['dashboard', 'guest', 'api'])->default('dashboard')->after('ai_cost');
            $table->string('guest_ip')->nullable()->after('source');
            $table->string('guest_session_id')->nullable()->after('guest_ip');
            $table->index(['guest_ip', 'created_at']);
            $table->index(['guest_session_id', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropIndex(['guest_ip', 'created_at']);
            $table->dropIndex(['guest_session_id', 'created_at']);
            $table->dropIndex(['source', 'created_at']);
            $table->dropColumn(['source', 'guest_ip', 'guest_session_id']);
        });
    }
};
