<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            if (!Schema::hasColumn('media_files', 'gallery_visible')) {
                $table->boolean('gallery_visible')->default(true)->after('title');
            }
        });

        DB::table('media_files')->update(['gallery_visible' => 0]);
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            if (Schema::hasColumn('media_files', 'gallery_visible')) {
                $table->dropColumn('gallery_visible');
            }
        });
    }
};
