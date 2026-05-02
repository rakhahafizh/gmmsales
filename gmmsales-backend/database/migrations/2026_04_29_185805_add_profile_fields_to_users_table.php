<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nomor_telepon', 15)->nullable()->after('username');
            $table->foreignId('wilayah_id')->nullable()->after('nomor_telepon')->constrained('wilayahs')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('photo_path')->nullable()->after('is_active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['wilayah_id']);
            $table->dropColumn(['nomor_telepon', 'wilayah_id', 'is_active', 'photo_path', 'deleted_at']);
        });
    }
};