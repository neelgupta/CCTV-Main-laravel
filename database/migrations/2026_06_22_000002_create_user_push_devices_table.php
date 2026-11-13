<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_push_devices')) {
            Schema::create('user_push_devices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('fcm_token_hash', 64)->unique();
                $table->longText('fcm_token');
                $table->string('browser_name', 100)->nullable();
                $table->string('platform_name', 100)->nullable();
                $table->string('user_agent', 1000)->nullable();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'company_id']);
            });

            return;
        }

        $database = DB::connection()->getDatabaseName();

        if (! Schema::hasColumn('user_push_devices', 'fcm_token_hash')) {
            Schema::table('user_push_devices', function (Blueprint $table): void {
                $table->string('fcm_token_hash', 64)->nullable()->after('company_id');
            });
        }

        if (! Schema::hasColumn('user_push_devices', 'fcm_token')) {
            Schema::table('user_push_devices', function (Blueprint $table): void {
                $table->longText('fcm_token')->nullable()->after('fcm_token_hash');
            });
        } else {
            DB::statement('ALTER TABLE `user_push_devices` MODIFY `fcm_token` LONGTEXT NOT NULL');
        }

        $hasLegacyTokenIndex = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'user_push_devices')
            ->where('index_name', 'user_push_devices_fcm_token_unique')
            ->exists();

        if ($hasLegacyTokenIndex) {
            DB::statement('ALTER TABLE `user_push_devices` DROP INDEX `user_push_devices_fcm_token_unique`');
        }

        DB::statement('UPDATE `user_push_devices` SET `fcm_token_hash` = SHA2(`fcm_token`, 256) WHERE `fcm_token_hash` IS NULL OR `fcm_token_hash` = ""');

        DB::statement('DELETE t1 FROM `user_push_devices` t1 INNER JOIN `user_push_devices` t2 ON t1.`fcm_token_hash` = t2.`fcm_token_hash` AND t1.`id` > t2.`id`');

        $hasHashIndex = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'user_push_devices')
            ->where('index_name', 'user_push_devices_fcm_token_hash_unique')
            ->exists();

        if (! $hasHashIndex) {
            DB::statement('ALTER TABLE `user_push_devices` ADD UNIQUE `user_push_devices_fcm_token_hash_unique` (`fcm_token_hash`)');
        }

        $hasCompositeIndex = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'user_push_devices')
            ->where('index_name', 'user_push_devices_user_id_company_id_index')
            ->exists();

        if (! $hasCompositeIndex) {
            DB::statement('ALTER TABLE `user_push_devices` ADD INDEX `user_push_devices_user_id_company_id_index` (`user_id`, `company_id`)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_push_devices');
    }
};
