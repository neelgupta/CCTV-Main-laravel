<x-app-layout>
    <x-slot name="header">
        <div class="border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold leading-tight text-slate-950">
                Edit Staff Member
            </h2>
            <p class="mt-1 text-sm text-slate-500">Update staff details with the same validation rules used when creating staff.</p>
        </div>
    </x-slot>

    @php
        $departmentOptions = ['HR', 'IT', 'Sales', 'Marketing'];
        $selectedDepartment = old('department', $staff->department);
        $joinDateValue = old('join_date', $staff->join_date ? \Illuminate\Support\Carbon::parse($staff->join_date)->format('Y-m-d') : '');
        $selectedRoleId = old('role_id', $staff->user?->role_id ?? $staff->role_id);
    @endphp

    <div class="py-4">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <div class="border border-slate-200 bg-white p-5 shadow-sm">
                <form method="POST" action="{{ route('teams.staff.update', $staff) }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-6">
                        <div>
                            <h3 class="border-b border-slate-100 pb-2 text-lg font-medium text-slate-950">Basic Information</h3>
                            <p class="mt-1 text-sm text-slate-500">Keep staff records accurate before updating or removing the profile.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <x-input-label for="linked_user" :value="__('Linked User')" />
                                <x-text-input
                                    id="linked_user"
                                    class="mt-1 block w-full  bg-slate-50"
                                    type="text"
                                    :value="$staff->user ? ($staff->user->name . ' (' . $staff->user->email . ')') : 'No linked user'"
                                    disabled
                                />
                                <p class="mt-1 text-xs text-slate-500">Linked login users cannot be changed from this screen.</p>
                            </div>

                            <div>
                                <x-input-label for="name" :value="__('Full Name')" />
                            <x-text-input id="name" class="mt-1 block w-full rounded-none" type="text" name="name" :value="old('name', $staff->name)" required minlength="2" maxlength="191" autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="contact_number" :value="__('Contact Number')" />
                            <x-text-input id="contact_number" class="mt-1 block w-full rounded-none" type="text" name="contact_number" :value="old('contact_number', $staff->contact_number)" required minlength="10" maxlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);" />
                                <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
                            </div>

                            @if (auth()->user()->isCompanyAdmin())
                                <div>
                                    <x-searchable-select
                                        name="role_id"
                                        id="role_id"
                                        label="Role"
                                        :options="$roles->map(fn ($role) => ['id' => $role->id, 'label' => $role->name])"
                                        :selected="$selectedRoleId"
                                        placeholder="Search role..."
                                        empty-label="Select Role"
                                    />
                                    <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                                </div>
                            @endif

                            <div>
                                <x-searchable-select
                                    name="department"
                                    id="department"
                                    label="Department"
                                    :options="collect($departmentOptions)->map(fn ($departmentOption) => ['id' => $departmentOption, 'label' => $departmentOption])"
                                    :selected="$selectedDepartment"
                                    placeholder="Search department..."
                                    empty-label="Select Department"
                                />
                                @if (filled($selectedDepartment) && !in_array($selectedDepartment, $departmentOptions, true))
                                    <input type="hidden" name="department" value="{{ $selectedDepartment }}">
                                @endif
                                <x-input-error :messages="$errors->get('department')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="join_date" :value="__('Joining Date')" />
                            <x-text-input id="join_date" class="mt-1 block w-full rounded-none" type="date" name="join_date" :value="$joinDateValue" required />
                                <x-input-error :messages="$errors->get('join_date')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="profile_photo" :value="__('Profile Photo')" />
                                <input id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-1 block w-full  border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500" />
                                <p class="mt-1 text-xs text-slate-500">Uploading a new photo will replace the existing one.</p>
                                <x-input-error :messages="$errors->get('profile_photo')" class="mt-2" />
                            </div>

                            @if ($staff->profile_photo_url)
                                <div class="md:col-span-2">
                                    <div class="flex items-center gap-4 border border-slate-200 bg-slate-50 p-4">
                                        <img src="{{ $staff->profile_photo_url }}" alt="{{ $staff->name }}" class="h-16 w-16 object-cover ring-2 ring-white">
                                        <div>
                                            <p class="text-sm font-medium text-slate-900">Current profile photo</p>
                                            <p class="text-xs text-slate-500">A new upload replaces this image automatically.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center justify-between border-t border-slate-100 pt-6">
                            <a href="{{ route('teams.staff.show', $staff) }}" class="text-sm text-slate-600 hover:text-slate-900">Back to Staff Details</a>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('teams.staff.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Cancel</a>
                                <x-primary-button class=" px-6 py-2">
                                    Update Staff Member
                                </x-primary-button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
