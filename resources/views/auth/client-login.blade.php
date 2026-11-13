<x-guest-layout>
    <div class="min-h-screen flex items-center justify-center bg-slate-950 px-4">
        <div class="w-full max-w-md rounded-3xl bg-white/5 p-8 text-white ring-1 ring-white/10">
            <h1 class="text-2xl font-bold">Client Login</h1>
            <p class="mt-2 text-sm text-slate-300">Sign in to view your project and account details.</p>

            <x-auth-session-status class="mb-4 mt-6 text-emerald-400 text-sm" :status="session('status')" />

            <form method="POST" action="{{ route('client.login.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus />
                </div>
                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required />
                </div>
                <x-primary-button class="w-full justify-center">
                    {{ __('Sign In') }}
                </x-primary-button>
            </form>
        </div>
    </div>
</x-guest-layout>
