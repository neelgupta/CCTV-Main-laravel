<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex h-[42px] items-center px-5 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:-translate-y-0.5 hover:bg-red-500 hover:shadow-md active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-250']) }}>
    {{ $slot }}
</button>
