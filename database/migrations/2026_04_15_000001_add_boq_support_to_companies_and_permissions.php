<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'enable_boq')) {
                $table->boolean('enable_boq')->default(false)->after('enable_quotation_generator');
            }
        });

        Schema::create('boq_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('boq_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('boq_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('unit', 50);
            $table->decimal('price', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'category_id']);
        });

        $now = now();
        $permissions = [
            ['key' => 'boq.categories.manage', 'name' => 'Manage BOQ Categories', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'boq.products.manage', 'name' => 'Manage BOQ Products', 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                ['name' => $permission['name'], 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('key', array_column($permissions, 'key'))->pluck('id', 'key');
        $adminRoleIds = DB::table('roles')->whereIn('slug', ['company_admin', 'companyadmin'])->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        $keys = ['boq.categories.manage', 'boq.products.manage'];
        $permissionIds = DB::table('permissions')->whereIn('key', $keys)->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Schema::dropIfExists('boq_products');
        Schema::dropIfExists('boq_categories');

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'enable_boq')) {
                $table->dropColumn('enable_boq');
            }
        });
    }
};
