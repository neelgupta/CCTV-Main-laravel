<section class="bg-white p-6 rounded-lg">
    <header class="border-b border-gray-100 pb-4 mb-6">
        <h2 class="text-lg font-bold text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form id="update-password-form" method="post" action="{{ route('password.update') }}" class="space-y-6">
        @csrf
        @method('put')

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <div>
                <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                <x-text-input id="update_password_current_password" placeholder="Enter Current Password" name="current_password" type="password" class="mt-1 block w-full bg-gray-50" />

                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password" :value="__('New Password')" />
                <x-text-input id="update_password_password" placeholder="Enter New Password" name="password" type="password" class="mt-1 block w-full bg-gray-50" />

                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" placeholder="Enter New Confirm Password" class="mt-1 block w-full bg-gray-50" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-4 border-t border-gray-100 pt-6">
            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-green-600 font-medium"
                >
                    <span class="mr-1">✓</span> {{ __('Password updated.') }}
                </p>
            @endif

            <x-primary-button class="min-w-[180px] justify-center py-2.5 shadow-md">
                {{ __('Update Password') }}
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
    const form = document.getElementById('update-password-form');
    const $form = $(form);

    $form.validate({
        rules: {
            current_password: {
                required: true,
            },
            password: {
                required: true,
                minlength: 8,
            },
            password_confirmation: {
                required: true,
                equalTo: '#update_password_password',
            },
        },
        messages: {
            current_password: {
                required: 'Please enter your current password.',
            },
            password: {
                required: 'Please enter a new password.',
                minlength: 'The new password must be at least 8 characters.',
            },
            password_confirmation: {
                required: 'Please confirm your new password.',
                equalTo: 'The password confirmation does not match.',
            },
        },
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!$form.valid()) {
            return;
        }

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async response => {
            const data = await response.json();

            if (response.ok) {
                Swal.fire({
                    title: 'Success!',
                    text: 'Your password has been changed.',
                    icon: 'success',
                    confirmButtonColor: '#1e293b'
                }).then(() => {
                    form.reset();
                    window.location.reload();
                });
            } else {
                let errorMessage = 'Validation failed.';

                if (data.errors) {
                    errorMessage = Object.values(data.errors).flat()[0];
                }

                Swal.fire({
                    title: 'Invalid Data',
                    text: errorMessage,
                    icon: 'error',
                    confirmButtonColor: '#1e293b'
                });
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Connection lost or server error.', 'error');
        });
    });
});
</script>
