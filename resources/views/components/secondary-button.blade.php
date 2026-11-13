<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex h-[42px] items-center px-5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:-translate-y-0.5 hover:bg-[#f8f8f8] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#c01917] focus:ring-offset-2 disabled:opacity-25 transition-all duration-250']) }}>
    {{ $slot }}
</button>
