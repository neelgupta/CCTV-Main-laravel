<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->string('storage_provider', 30)->default('local')->after('disk'); // local | company_drive | super_admin_drive
            $table->string('drive_account_email')->nullable()->after('storage_provider');
            $table->string('drive_account_id')->nullable()->after('drive_account_email');
            $table->string('drive_folder_id')->nullable()->after('drive_account_id');
            $table->string('drive_file_id')->nullable()->after('drive_folder_id');
            $table->string('storage_path_label', 1000)->nullable()->after('drive_file_id');
            $table->string('upload_source')->nullable()->after('storage_path_label');
            $table->foreignId('drive_connection_id')->nullable()->after('upload_source');
            $table->timestamp('uploaded_at')->nullable()->after('drive_connection_id');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn([
                'storage_provider',
                'drive_account_email',
                'drive_account_id',
                'drive_folder_id',
                'drive_file_id',
                'storage_path_label',
                'upload_source',
                'drive_connection_id',
                'uploaded_at',
            ]);
        });
    }
};
