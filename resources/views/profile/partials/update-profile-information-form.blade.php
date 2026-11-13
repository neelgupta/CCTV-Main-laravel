<section class="bg-white p-6 rounded-lg">
    <header class="border-b border-gray-100 pb-4 mb-6">
        <h2 class="text-lg font-bold text-gray-900">
            {{ __('Profile Information') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-6" enctype="multipart/form-data" id="profile-form">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            {{-- Column 1: Core User Info --}}
            <div class="space-y-4">
                <div>
                    <x-input-label for="name" :value="__('Full Name')" />
                    <x-text-input id="name" name="name" type="text" placeholder="Enter Name" class="mt-1 block w-full bg-gray-50" :value="old('name', $user->name)" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="email" :value="__('Email Address')" />
                    <x-text-input id="email" name="email" type="email" placeholder="Enter Email " class="mt-1 block w-full bg-gray-50" :value="old('email', $user->email)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                </div>

                {{-- If Super Admin, Profile Image shows up here alone --}}
                @if ($user->isSuperAdmin())
                    <div>
                        <x-input-label for="profile_photo" :value="__('Profile Image')" />
                        <div class="mt-1 flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <img
                                    id="profile-photo-preview"
                                    src="{{ $user->profile_photo_url }}"
                                    class="w-[42px] h-[42px] object-cover rounded-full border border-gray-200 shadow-sm {{ $user->profile_photo ? '' : 'hidden' }}"
                                    alt="Profile image preview">
                            </div>
                            <div class="flex-1">
                                <label for="profile_photo" class="flex items-center justify-between gap-3 rounded-md border-2 border-dashed border-indigo-200 bg-indigo-50/50 px-3 py-2 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition h-[42px]">
                                    <span id="profile-photo-file-name" class="text-xs font-medium text-indigo-700 truncate">Change Image</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                </label>
                                <input id="profile_photo" name="profile_photo" type="file" accept="image/*" class="sr-only" />
                            </div>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />
                    </div>
                @endif
            </div>

            {{-- If regular user, Columns 2 & 3 handle the Company/Extra Details --}}
            @if (!$user->isSuperAdmin())
                {{-- Column 2: Phone and Logo --}}
                <div class="space-y-4">
                    <div>
                        <x-input-label for="phone_number" :value="__('Phone Number')" />
                        <x-text-input id="phone_number" name="phone_number" type="text" placeholder="Enter Phone Number"
                            class="mt-1 block w-full bg-gray-50"
                            :value="old('phone_number', $company->phone_number)"
                            required
                            maxlength="10"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone_number')" />
                    </div>

                    <div>
                        <x-input-label for="logo" :value="__('Company Logo')" />
                        <div class="mt-1 flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <img
                                    id="logo-preview"
                                    src="{{ $company->logo_url }}"
                                    class="w-[42px] h-[42px] object-cover rounded border border-gray-200 shadow-sm {{ $company->logo ? '' : 'hidden' }}"
                                    alt="Company logo preview">
                            </div>
                            <div class="flex-1">
                                <label for="logo" class="flex items-center justify-between gap-3 rounded-md border-2 border-dashed border-indigo-200 bg-indigo-50/50 px-3 py-2 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition h-[42px]">
                                    <span id="logo-file-name" class="text-xs font-medium text-indigo-700 truncate">Change Logo</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                </label>
                                <input id="logo" name="logo" type="file" accept="image/*" class="sr-only" />
                            </div>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('logo')" />
                    </div>
                </div>

                {{-- Column 3: Profile Image and Full Address (Reduced length/height) --}}
                <div class="space-y-4">
                    <div>
                        <x-input-label for="profile_photo" :value="__('Profile Image')" />
                        <div class="mt-1 flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <img
                                    id="profile-photo-preview"
                                    src="{{ $user->profile_photo_url }}"
                                    class="w-[42px] h-[42px] object-cover rounded-full border border-gray-200 shadow-sm {{ $user->profile_photo ? '' : 'hidden' }}"
                                    alt="Profile image preview">
                            </div>
                            <div class="flex-1">
                                <label for="profile_photo" class="flex items-center justify-between gap-3 rounded-md border-2 border-dashed border-indigo-200 bg-indigo-50/50 px-3 py-2 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition h-[42px]">
                                    <span id="profile-photo-file-name" class="text-xs font-medium text-indigo-700 truncate">Change Image</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                </label>
                                <input id="profile_photo" name="profile_photo" type="file" accept="image/*" class="sr-only" />
                            </div>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />
                    </div>

                    <div>
                        <x-input-label for="address" :value="__('Full Address')" />
                        <textarea id="address" name="address" placeholder="Enter Address" required autocomplete="off"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm bg-gray-50 text-sm"
                            rows="2">{{ old('address', $company->address) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('address')" />
                    </div>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-end gap-4 border-t border-gray-100 pt-6">
            <x-primary-button id="submit-btn" class="min-w-[180px] justify-center py-2.5 shadow-md">
                {{ __('Update Profile') }}
            </x-primary-button>
        </div>
    </form>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery || !jQuery.fn || typeof jQuery.fn.validate !== 'function') {
            return;
        }

        const $ = window.jQuery;
        const profileForm = document.getElementById('profile-form');
        const isSuperAdmin = @js($user->isSuperAdmin());
        const $profileForm = $(profileForm);
        const initialProfileValues = {
            name: ($('#name').val() || '').trim(),
            email: ($('#email').val() || '').trim(),
            phone_number: isSuperAdmin ? '' : (($('#phone_number').val() || '').trim()),
            address: isSuperAdmin ? '' : (($('#address').val() || '').trim()),
            logo: '',
            profile_photo: ''
        };

        $profileForm.validate({
            rules: {
                name: {
                    required: true,
                    minlength: 2,
                },
                email: {
                    required: true,
                    email: true,
                },
                phone_number: {
                    required: !isSuperAdmin,
                    digits: true,
                    minlength: 10,
                    maxlength: 10,
                },
                address: {
                    required: !isSuperAdmin,
                    minlength: 5,
                },
                logo: {
                    extension: "jpg|jpeg|png|webp",
                },
                profile_photo: {
                    extension: "jpg|jpeg|png|webp",
                },
            },
            messages: {
                name: {
                    required: 'Please enter your full name.',
                    minlength: 'The full name must be at least 2 characters.',
                },
                email: {
                    required: 'Please enter your email address.',
                    email: 'Please enter a valid email address.',
                },
                phone_number: {
                    required: 'Please enter your phone number.',
                    digits: 'The phone number must contain only digits.',
                    minlength: 'The phone number must be exactly 10 digits.',
                    maxlength: 'The phone number must be exactly 10 digits.',
                },
                address: {
                    required: 'Please enter your full address.',
                    minlength: 'The address must be at least 5 characters.',
                },
                logo: {
                    extension: 'Only JPG, JPEG, PNG, or WEBP images are allowed.',
                },
                profile_photo: {
                    extension: 'Only JPG, JPEG, PNG, or WEBP images are allowed.',
                },
            },
        });

        // 1. Success Alert (Triggered after page reload if session exists)
        @if (session('status') === 'profile-updated')
            Swal.fire({
                title: 'Updated!',
                text: 'Your profile information has been updated successfully.',
                icon: 'success',
                confirmButtonColor: '#1e293b',
                timer: 3000
            });
        @endif

        // 2. Logo Preview Logic
        if (!isSuperAdmin) {
            const logoInput = document.getElementById('logo');
            if (logoInput) {
                logoInput.addEventListener('change', function() {
                    const fileName = this.files[0]?.name || 'Change Logo';
                    document.getElementById('logo-file-name').textContent = fileName;

                    const preview = document.getElementById('logo-preview');
                    if (this.files && this.files[0]) {
                        const url = URL.createObjectURL(this.files[0]);
                        preview.src = url;
                        preview.classList.remove('hidden');
                    }
                });
            }
        }

        const profilePhotoInput = document.getElementById('profile_photo');
        if (profilePhotoInput) {
            profilePhotoInput.addEventListener('change', function() {
                const fileName = this.files[0]?.name || 'Change Image';
                document.getElementById('profile-photo-file-name').textContent = fileName;

                const preview = document.getElementById('profile-photo-preview');
                if (this.files && this.files[0]) {
                    const url = URL.createObjectURL(this.files[0]);
                    preview.src = url;
                    preview.classList.remove('hidden');
                }
            });
        }

        // 3. Additional File Validation and Alerts
        profileForm.addEventListener('submit', function(e) {
            if (profileForm.dataset.submitted === '1') {
                return;
            }

            const currentProfileValues = {
                name: ($('#name').val() || '').trim(),
                email: ($('#email').val() || '').trim(),
                phone_number: isSuperAdmin ? '' : (($('#phone_number').val() || '').trim()),
                address: isSuperAdmin ? '' : (($('#address').val() || '').trim()),
                logo: isSuperAdmin ? '' : ($('#logo').val() || ''),
                profile_photo: $('#profile_photo').val() || ''
            };

            const hasProfileChanges =
                currentProfileValues.name !== initialProfileValues.name ||
                currentProfileValues.email !== initialProfileValues.email ||
                currentProfileValues.phone_number !== initialProfileValues.phone_number ||
                currentProfileValues.address !== initialProfileValues.address ||
                currentProfileValues.logo !== initialProfileValues.logo ||
                currentProfileValues.profile_photo !== initialProfileValues.profile_photo;

            if (!hasProfileChanges) {
                e.preventDefault();
                return Swal.fire({
                    title: 'No Changes to Update',
                    text: 'Please change at least one profile field before updating.',
                    icon: 'info',
                    confirmButtonColor: '#1e293b'
                });
            }

            // Get Field Values
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            // --- Validation Logic ---

            // Check Empty Fields
            if (!name || !email) {
                e.preventDefault();
                return Swal.fire({
                    title: 'Empty Fields',
                    text: 'Please ensure the required profile fields are filled out.',
                    icon: 'warning',
                    confirmButtonColor: '#1e293b'
                });
            }

            // Validate Email Format
            if (!emailRegex.test(email)) {
                e.preventDefault();
                return Swal.fire({
                    title: 'Invalid Email',
                    text: 'Please enter a valid email address.',
                    icon: 'error',
                    confirmButtonColor: '#1e293b'
                });
            }

            if (!isSuperAdmin) {
                const phone = document.getElementById('phone_number').value.trim();
                const address = document.getElementById('address').value.trim();

                // Validate required company fields
                if (!phone || !address) {
                    e.preventDefault();
                    return Swal.fire({
                        title: 'Empty Fields',
                        text: 'Please ensure all company profile fields are filled out.',
                        icon: 'warning',
                        confirmButtonColor: '#1e293b'
                    });
                }

                // Validate Phone Length
                if (phone.length !== 10) {
                    e.preventDefault();
                    return Swal.fire({
                        title: 'Invalid Phone Number',
                        text: 'The contact number must be exactly 10 digits.',
                        icon: 'warning',
                        confirmButtonColor: '#1e293b'
                    });
                }

                // Optional: Validate Logo File Size (e.g., max 2MB)
                const logoInput = document.getElementById('logo');
                const logoFile = logoInput?.files?.[0];
                if (logoFile && logoFile.size > 2 * 1024 * 1024) {
                    e.preventDefault();
                    return Swal.fire({
                        title: 'File Too Large',
                        text: 'The company logo must not exceed 2MB.',
                        icon: 'error',
                        confirmButtonColor: '#1e293b'
                    });
                }
            }

            const profilePhotoFile = document.getElementById('profile_photo')?.files?.[0];
            if (profilePhotoFile && profilePhotoFile.size > 2 * 1024 * 1024) {
                e.preventDefault();
                return Swal.fire({
                    title: 'File Too Large',
                    text: 'The profile image must not exceed 2MB.',
                    icon: 'error',
                    confirmButtonColor: '#1e293b'
                });
            }
        });

        // 4. Server-Side Error Catch (for things like "Email already taken")
        @if ($errors->any())
            Swal.fire({
                title: 'Validation Error',
                text: "{{ $errors->first() }}",
                icon: 'error',
                confirmButtonColor: '#1e293b'
            });
        @endif
    });
</script>

@if(filled(config('services.google_maps.key')) && ! $user->isSuperAdmin())
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const addressInput = document.getElementById('address');
            if (!addressInput || !window.google?.maps?.places) {
                return;
            }

            const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                componentRestrictions: { country: ['in'] },
                fields: ['formatted_address'],
            });

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place || !place.formatted_address) {
                    return;
                }

                addressInput.value = place.formatted_address;
                addressInput.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    </script>
@endif
