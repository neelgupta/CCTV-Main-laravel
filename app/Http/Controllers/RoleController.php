<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    private function roleQuery(int $companyId)
    {
        return Role::query()
            ->where('company_id', $companyId)
            ->withCount('users')
            ->with('permissions:id,key,name')
            ->orderBy('name');
    }

    private function companyAdminPermissionIds(): array
    {
        return Permission::query()->pluck('id')->all();
    }

    private function permissionMatrix()
    {
        return Permission::query()
            ->where('key', '!=', 'all.access')
            ->orderBy('key')
            ->get(['id', 'key', 'name'])
            ->groupBy(function ($permission) {
                $feature = Str::before((string) $permission->key, '.');
                if (in_array($feature, ['support', 'tickets'], true)) {
                    return 'Support';
                }

                if ($feature === 'tasks') {
                    return 'Tasks';
                }

                return Str::headline(str_replace('_', ' ', $feature));
            })
            ->map(function ($group) {
                return $group
                    ->sortBy(function ($permission) {
                        $canonicalOrder = [
                            'support.view' => 1,
                            'support.create' => 2,
                            'support.edit' => 3,
                            'support.delete' => 4,
                            'tasks.view' => 1,
                            'tasks.manage' => 2,
                            'appointments.view' => 1,
                            'appointments.manage' => 2,
                        ];

                        return $canonicalOrder[(string) $permission->key] ?? 99;
                    })
                    ->map(function ($permission) {
                        $capability = Str::contains((string) $permission->key, '.')
                            ? Str::after((string) $permission->key, '.')
                            : (string) $permission->key;

                        return [
                            'id' => $permission->id,
                            'key' => $permission->key,
                            'label' => Str::headline(str_replace('_', ' ', $capability)),
                        ];
                    })->values();
            });
    }

    private function validateRole(Request $request, int $companyId, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:50',
                Rule::unique('roles', 'name')
                    ->ignore($role?->id)
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);
    }

    private function ensureRoleBelongsToCompany(Role $role, int $companyId): void
    {
        if ((int) $role->company_id !== $companyId) {
            abort(403);
        }
    }

    public function index()
    {
        $companyId = auth()->user()->company_id;
        $roles = $this->roleQuery($companyId)->get();

        return view('settings.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissionMatrix = $this->permissionMatrix();

        return view('settings.roles.create', [
            'role' => new Role(),
            'permissionMatrix' => $permissionMatrix,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $validated = $this->validateRole($request, $companyId);

        $slug = Str::slug($validated['name'], '_');
        if ($slug === '') {
            $slug = 'role_' . now()->timestamp;
        }

        $exists = Role::query()
            ->where('company_id', $companyId)
            ->where('slug', $slug)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'A role with this name already exists.'])->withInput();
        }

        $role = Role::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        if ($role->isCompanyAdmin()) {
            $role->permissions()->sync($this->companyAdminPermissionIds());
        } else {
            $role->permissions()->sync($validated['permissions'] ?? []);
        }

        return redirect()->route('settings.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $companyId = auth()->user()->company_id;
        $this->ensureRoleBelongsToCompany($role, $companyId);

        $role->load('permissions:id,key,name');
        $permissionMatrix = $this->permissionMatrix();

        return view('settings.roles.edit', compact('role', 'permissionMatrix'));
    }

    public function update(Request $request, Role $role)
    {
        $companyId = auth()->user()->company_id;
        $this->ensureRoleBelongsToCompany($role, $companyId);

        $validated = $this->validateRole($request, $companyId, $role);

        $role->name = $validated['name'];
        if (!$role->isCompanyAdmin()) {
            $slug = Str::slug($validated['name'], '_');
            $role->slug = $slug !== '' ? $slug : ('role_' . $role->id);
        }
        $role->save();

        if ($role->isCompanyAdmin()) {
            $role->permissions()->sync($this->companyAdminPermissionIds());
        } else {
            $role->permissions()->sync($validated['permissions'] ?? []);
        }

        return redirect()->route('settings.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        $companyId = auth()->user()->company_id;
        $this->ensureRoleBelongsToCompany($role, $companyId);

        if ($role->isCompanyAdmin()) {
            return redirect()->route('settings.roles.index')->with('error', 'Company Admin role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('settings.roles.index')->with('error', 'This role is assigned to users and cannot be deleted.');
        }

        $role->slug = $role->slug . '_deleted_' . now()->timestamp;
        $role->save();
        $role->delete();

        return redirect()->route('settings.roles.index')->with('success', 'Role deleted successfully.');
    }
}
