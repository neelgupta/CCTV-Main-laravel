<x-guest-layout>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[#0B132B]">
        <div class="w-full sm:max-w-md mt-6 px-8 py-10 bg-[#111A36] border border-[#1E294B] shadow-2xl rounded-3xl backdrop-blur-sm">

            <div class="flex flex-col items-center mb-8">
                <div class="w-14 h-14 bg-gradient-to-tr from-[#2563EB] to-[#06B6D4] rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20 mb-4">
                    <svg class="w-7 h-7 text-white animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 10h-1V7c0-3.31-2.69-6-6-6S6 3.69 6 7v3H5c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zm-7 6c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm4-6H8V7c0-2.21 1.79-4 4-4s4 1.79 4 4v3z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white tracking-tight">Reset Password</h2>
                <p class="text-sm text-gray-400 mt-1 text-center">Enter your email and create a secure new password.</p>
            </div>

            <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div>
                    <label for="email" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Email Address</label>
                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email', $request->email) }}"
                           required
                           autofocus
                           autocomplete="username"
                           class="block w-full px-4 py-3 bg-[#EAF0F6] border-2 border-transparent text-[#111A36] font-medium rounded-xl focus:outline-none focus:border-[#7C3AED] focus:ring-4 focus:ring-purple-500/20 transition duration-200 placeholder-gray-400"
                           placeholder="example@gmail.com" />

                    @if($errors->has('email'))
                        <div class="text-sm text-red-400 mt-1.5 font-medium flex items-center gap-1">
                            <span class="inline-block w-1 h-1 rounded-full bg-red-400"></span>
                            {{ $errors->first('email') }}
                        </div>
                    @endif
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">New Password</label>
                    <input id="password"
                           type="password"
                           name="password"
                           required
                           autocomplete="new-password"
                           class="block w-full px-4 py-3 bg-[#EAF0F6] border-2 border-transparent text-[#111A36] font-medium rounded-xl focus:outline-none focus:border-[#7C3AED] focus:ring-4 focus:ring-purple-500/20 transition duration-200"
                           placeholder="••••••••" />

                    @if($errors->has('password'))
                        <div class="text-sm text-red-400 mt-1.5 font-medium flex items-center gap-1">
                            <span class="inline-block w-1 h-1 rounded-full bg-red-400"></span>
                            {{ $errors->first('password') }}
                        </div>
                    @endif
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Confirm Password</label>
                    <input id="password_confirmation"
                           type="password"
                           name="password_confirmation"
                           required
                           autocomplete="new-password"
                           class="block w-full px-4 py-3 bg-[#EAF0F6] border-2 border-transparent text-[#111A36] font-medium rounded-xl focus:outline-none focus:border-[#7C3AED] focus:ring-4 focus:ring-purple-500/20 transition duration-200"
                           placeholder="••••••••" />

                    @if($errors->has('password_confirmation'))
                        <div class="text-sm text-red-400 mt-1.5 font-medium flex items-center gap-1">
                            <span class="inline-block w-1 h-1 rounded-full bg-red-400"></span>
                            {{ $errors->first('password_confirmation') }}
                        </div>
                    @endif
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-[#2563EB] to-[#06B6D4] hover:from-[#1D4ED8] hover:to-[#0891B2] text-white font-bold uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 hover:shadow-cyan-500/30 transform hover:-translate-y-0.5 active:translate-y-0 transition duration-150 ease-in-out focus:outline-none focus:ring-4 focus:ring-cyan-500/30">
                        Reset Password
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-400">
                    Remember your password?
                    <a href="{{ route('login') }}" class="text-[#06B6D4] hover:text-[#2563EB] font-semibold transition duration-150 ease-in-out underline decoration-transparent hover:decoration-inherit">
                        Sign In
                    </a>
                </p>
            </div>

        </div>
    </div>
</x-guest-layout>
