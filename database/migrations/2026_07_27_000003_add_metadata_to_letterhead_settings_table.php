<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letterhead_settings', function (Blueprint $table) {
            $table->string('source_filename')->nullable()->after('file_path');
            $table->unsignedSmallInteger('page_count')->nullable()->after('source_filename');
            $table->decimal('page_width_mm', 8, 2)->nullable()->after('page_count');
            $table->decimal('page_height_mm', 8, 2)->nullable()->after('page_width_mm');
            $table->char('page_orientation', 1)->nullable()->after('page_height_mm');
        });
    }

    public function down(): void
    {
        Schema::table('letterhead_settings', function (Blueprint $table) {
            $table->dropColumn([
                'source_filename',
                'page_count',
                'page_width_mm',
                'page_height_mm',
                'page_orientation',
            ]);
        });
    }
};
