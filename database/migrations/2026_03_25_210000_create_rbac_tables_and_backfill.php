<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['permission_id', 'role_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
        });

        $now = now();
        $permissions = [
            ['key' => 'all.access', 'name' => 'All Access', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'dashboard.view', 'name' => 'View Dashboard', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.view', 'name' => 'View Leads', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.create', 'name' => 'Create Leads', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.edit', 'name' => 'Edit Leads', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.delete', 'name' => 'Delete Leads', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.status.update', 'name' => 'Update Lead Status', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.comment', 'name' => 'Comment On Leads', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.quotation.manage', 'name' => 'Manage Lead Quotations', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.survey.upload', 'name' => 'Upload Lead Survey', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.documents.upload', 'name' => 'Upload Lead Documents', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'leads.summary.download', 'name' => 'Download Lead Summary', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'projects.view', 'name' => 'View Projects', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'projects.create', 'name' => 'Create Projects', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'projects.edit', 'name' => 'Edit Projects', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'projects.delete', 'name' => 'Delete Projects', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'projects.stage.update', 'name' => 'Update Project Stages', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'projects.assets.manage', 'name' => 'Manage Project Assets', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'projects.comment', 'name' => 'Comment On Projects', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'tickets.view', 'name' => 'View Tickets', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'tickets.create', 'name' => 'Create Tickets', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'tickets.reply', 'name' => 'Reply to Tickets', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'tickets.manage', 'name' => 'Manage Tickets', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'settings.statuses.manage', 'name' => 'Manage Lead Statuses', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'settings.categories.manage', 'name' => 'Manage Categories', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'settings.roles.manage', 'name' => 'Manage Roles', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'settings.users.manage', 'name' => 'Manage Company Users', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'boq.categories.manage', 'name' => 'Manage BOQ Categories', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'boq.products.manage', 'name' => 'Manage BOQ Products', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('permissions')->insert($permissions);
        $permissionIds = DB::table('permissions')->pluck('id', 'key');

        $superAdminRoleId = DB::table('roles')->insertGetId([
            'company_id' => null,
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permission_role')->insert([
            'permission_id' => $permissionIds['all.access'],
            'role_id' => $superAdminRoleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $companies = DB::table('companies')->select('id')->get();
        foreach ($companies as $company) {
            $adminRoleId = DB::table('roles')->insertGetId([
                'company_id' => $company->id,
                'name' => 'Company Admin',
                'slug' => 'company_admin',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $employeeRoleId = DB::table('roles')->insertGetId([
                'company_id' => $company->id,
                'name' => 'Employee',
                'slug' => 'employee',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $adminKeys = [
                'dashboard.view',
                'leads.view', 'leads.create', 'leads.edit', 'leads.delete', 'leads.status.update', 'leads.comment',
                'leads.quotation.manage', 'leads.survey.upload', 'leads.documents.upload', 'leads.summary.download',
                'projects.view', 'projects.create', 'projects.edit', 'projects.delete', 'projects.stage.update',
                'projects.assets.manage', 'projects.comment',
                'tickets.view', 'tickets.create', 'tickets.reply',
                'settings.statuses.manage', 'settings.categories.manage', 'settings.roles.manage', 'settings.users.manage',
                'boq.categories.manage', 'boq.products.manage',
            ];

            $employeeKeys = [
                'dashboard.view',
                'leads.view', 'leads.create', 'leads.edit', 'leads.status.update', 'leads.comment',
                'leads.quotation.manage', 'leads.survey.upload', 'leads.documents.upload', 'leads.summary.download',
                'projects.view', 'projects.create', 'projects.edit', 'projects.stage.update', 'projects.assets.manage', 'projects.comment',
                'tickets.view', 'tickets.create', 'tickets.reply',
            ];

            $adminPivot = [];
            foreach ($adminKeys as $key) {
                $adminPivot[] = [
                    'permission_id' => $permissionIds[$key],
                    'role_id' => $adminRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('permission_role')->insert($adminPivot);

            $employeePivot = [];
            foreach ($employeeKeys as $key) {
                $employeePivot[] = [
                    'permission_id' => $permissionIds[$key],
                    'role_id' => $employeeRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('permission_role')->insert($employeePivot);

            $companyUsers = DB::table('users')->where('company_id', $company->id)->select('id', 'role')->get();
            foreach ($companyUsers as $user) {
                $normalized = Str::lower(trim((string) $user->role));
                $roleId = in_array($normalized, ['company_admin', 'companyadmin'], true) ? $adminRoleId : $employeeRoleId;
                DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
            }
        }

        DB::table('users')
            ->whereNull('company_id')
            ->whereIn('role', ['super_admin', 'admin'])
            ->update(['role_id' => $superAdminRoleId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
