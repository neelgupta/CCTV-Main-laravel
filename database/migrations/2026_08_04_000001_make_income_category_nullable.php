<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('incomes') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `incomes` DROP FOREIGN KEY `incomes_category_id_foreign`');
            DB::statement('ALTER TABLE `incomes` MODIFY `category_id` BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE `incomes` ADD CONSTRAINT `incomes_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `income_categories` (`id`) ON DELETE RESTRICT');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('incomes') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `incomes` DROP FOREIGN KEY `incomes_category_id_foreign`');
            DB::statement('ALTER TABLE `incomes` MODIFY `category_id` BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE `incomes` ADD CONSTRAINT `incomes_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `income_categories` (`id`) ON DELETE RESTRICT');
        }
    }
};
