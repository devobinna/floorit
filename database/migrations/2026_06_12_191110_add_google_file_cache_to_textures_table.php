<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textures', function (Blueprint $table) {
            $table->string('google_file_uri')->nullable()->after('thumbnail_path');
            $table->timestamp('google_file_expires_at')->nullable()->after('google_file_uri');
        });
    }

    public function down(): void
    {
        Schema::table('textures', function (Blueprint $table) {
            $table->dropColumn(['google_file_uri', 'google_file_expires_at']);
        });
    }
};
