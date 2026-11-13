<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes()->after('remember_token');
            });
        }

        $legacyUniqueIndex = $this->findUniqueIndexName('users', ['email']);
        if ($legacyUniqueIndex !== null) {
            Schema::table('users', function (Blueprint $table) use ($legacyUniqueIndex) {
                $table->dropUnique($legacyUniqueIndex);
            });
        }

        if (! $this->hasUniqueIndex('users', ['email', 'deleted_at'])) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique(['email', 'deleted_at']);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasUniqueIndex('users', ['email', 'deleted_at'])) {
            $compositeUniqueIndex = $this->findUniqueIndexName('users', ['email', 'deleted_at']);

            if ($compositeUniqueIndex !== null) {
                Schema::table('users', function (Blueprint $table) use ($compositeUniqueIndex) {
                    $table->dropUnique($compositeUniqueIndex);
                });
            }
        }

        if (! $this->hasUniqueIndex('users', ['email'])) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });
        }

        if (Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    private function hasUniqueIndex(string $table, array $columns): bool
    {
        return $this->findUniqueIndexName($table, $columns) !== null;
    }

    private function findUniqueIndexName(string $table, array $columns): ?string
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) !== true) {
                continue;
            }

            if (($index['columns'] ?? []) === $columns) {
                return $index['name'] ?? null;
            }
        }

        return null;
    }
};
