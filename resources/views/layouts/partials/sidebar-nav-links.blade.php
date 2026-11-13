@foreach ($navLinks as $link)
    @if (! empty($link['children']))
        <div x-data="{ expanded: {{ ($link['expanded'] ?? false) ? 'true' : 'false' }} }">
            <button
                type="button"
                @click="expanded = ! expanded"
                class="{{ ($link['active'] ?? false) ? 'bg-[#C22032] text-white font-semibold shadow-lg shadow-rose-900/20' : 'text-slate-600 font-medium hover:bg-rose-50 hover:text-[#C22032] hover:translate-x-0.5' }} group flex w-full items-center gap-3.5 px-4 py-0.5 text-sm transition-all duration-250"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center transition-colors duration-250 {{ ($link['active'] ?? false) ? 'bg-white/15 text-white shadow-sm' : 'bg-slate-100 text-slate-400 group-hover:bg-[#C22032] group-hover:text-white' }}">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="{{ $link['icon'] }}"></path>
                    </svg>
                </span>
                <span class="flex-1 text-left">{{ $link['label'] }}</span>
                <svg
                    class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-250"
                    :class="{ 'rotate-90': expanded }"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M9 6l6 6-6 6"></path>
                </svg>
            </button>

            <div
                x-show="expanded"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="mt-1.5 space-y-1 border-l-2 border-slate-200 pl-4 ml-8"
                style="display: none;"
            >
                @foreach ($link['children'] as $child)
                    <a
                        href="{{ $child['route'] }}"
                        @if (! empty($mobile))
                            @click="open = false"
                        @endif
                        class="{{ ($child['active'] ?? false) ? 'bg-[#d63384] text-white font-semibold shadow-lg shadow-rose-900/20' : 'text-slate-500 font-medium hover:bg-rose-50 hover:text-[#C22032] hover:translate-x-0.5' }} flex items-center gap-3 px-3.5 py-2.5 text-sm transition-all duration-250"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ ($child['active'] ?? false) ? 'bg-white' : 'bg-slate-300' }}"></span>
                        <span>{{ $child['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <a
            href="{{ $link['route'] }}"
            @if (! empty($mobile))
                @click="open = false"
            @endif
            class="{{ ($link['active'] ?? false) ? 'bg-[#C22032] text-white font-semibold shadow-lg shadow-rose-900/20' : 'text-slate-600 font-medium hover:bg-rose-50 hover:text-[#C22032] hover:translate-x-0.5' }} group flex items-center gap-3.5 px-4 py-0.5 text-sm transition-all duration-250"
        >
            <span class="flex h-8 w-8 shrink-0 items-center justify-center transition-colors duration-250 {{ ($link['active'] ?? false) ? 'bg-white/15 text-white shadow-sm' : 'bg-slate-100 text-slate-400 group-hover:bg-[#C22032] group-hover:text-white' }}">
                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="{{ $link['icon'] }}"></path>
                </svg>
            </span>
            <span>{{ $link['label'] }}</span>
        </a>
    @endif
@endforeach
