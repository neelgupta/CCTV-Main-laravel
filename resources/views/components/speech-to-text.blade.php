@props([
    'target',
    'defaultLanguage' => 'en-IN',
    'class' => '',
    'compact' => false,
])

<div
    x-data="speechToText({ targetId: '{{ $target }}', defaultLanguage: '{{ $defaultLanguage }}' })"
    class="speech-to-text-container inline-flex flex-col {{ $class }}"
>
    <div class="flex flex-wrap items-center gap-1.5">
        <!-- Language Selector -->
        <select
            x-model="selectedLanguage"
            @change="changeLanguage($event.target.value)"
            :disabled="!isSupported"
            class="{{ $compact ? 'h-6 text-[11px] px-1.5 py-0' : 'h-7 text-xs px-2 py-0.5' }} rounded-md border border-slate-200 bg-white text-slate-700 shadow-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
            title="Select speech language"
            aria-label="Speech language"
        >
            <template x-for="lang in languages" :key="lang.code">
                <option :value="lang.code" x-text="lang.label" :selected="lang.code === selectedLanguage"></option>
            </template>
        </select>

        <!-- Idle Microphone Button -->
        <button
            type="button"
            x-show="isSupported && !isListening"
            @click="toggleListening()"
            :disabled="isStarting"
            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white {{ $compact ? 'h-6 px-1.5 text-[11px]' : 'h-7 px-2.5 text-xs' }} font-medium text-slate-700 shadow-xs transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
            title="Start voice typing (appends speech to description)"
            aria-label="Start voice typing"
        >
            <svg class="{{ $compact ? 'h-3 w-3' : 'h-3.5 w-3.5' }} text-indigo-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
            </svg>
            @if(!$compact)
                <span>Voice Type</span>
            @endif
        </button>

        <!-- Active Listening / Stop Button -->
        <button
            type="button"
            x-show="isSupported && isListening"
            x-cloak
            @click="stopListening()"
            class="inline-flex items-center gap-1.5 rounded-md border border-rose-300 bg-rose-50 {{ $compact ? 'h-6 px-1.5 text-[11px]' : 'h-7 px-2.5 text-xs' }} font-medium text-rose-700 shadow-xs transition hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-1"
            title="Stop voice typing"
            aria-label="Stop voice typing"
        >
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-rose-600"></span>
            </span>
            <svg class="h-3 w-3 text-rose-700" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="6" y="6" width="12" height="12" rx="2" />
            </svg>
            <span>Stop</span>
        </button>

        <!-- Listening status text -->
        <span
            x-show="isSupported && isListening"
            x-cloak
            class="inline-flex items-center gap-1 text-[11px] font-medium text-rose-600 animate-pulse"
        >
            Listening...
        </span>

        <!-- Browser Unsupported fallback -->
        <button
            type="button"
            x-show="!isSupported"
            x-cloak
            disabled
            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-100 {{ $compact ? 'h-6 px-1.5 text-[11px]' : 'h-7 px-2.5 text-xs' }} font-medium text-slate-400 opacity-70 cursor-not-allowed"
            title="Speech recognition is not supported in this browser. Please use Chrome or Edge."
            aria-label="Speech recognition not supported"
        >
            <svg class="{{ $compact ? 'h-3 w-3' : 'h-3.5 w-3.5' }} text-slate-400" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
            </svg>
            @if(!$compact)
                <span>Voice Type</span>
            @endif
        </button>
    </div>

    <!-- Live Interim Feedback (Preview without modifying textarea) -->
    <div
        x-show="isListening && interimTranscript"
        x-cloak
        class="mt-1 flex items-center gap-1.5 rounded bg-slate-50 px-2 py-0.5 text-[11px] text-slate-600 border border-slate-200"
    >
        <span class="font-semibold text-slate-500">Hearing:</span>
        <span class="italic text-slate-700 truncate max-w-xs" x-text="interimTranscript"></span>
    </div>

    <!-- Clear Error Message -->
    <div
        x-show="errorMessage"
        x-cloak
        class="mt-1.5 flex items-center justify-between gap-2 rounded border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs text-rose-700"
        role="alert"
    >
        <div class="flex items-center gap-1.5">
            <svg class="h-4 w-4 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span x-text="errorMessage"></span>
        </div>
        <button
            type="button"
            @click="clearError()"
            class="text-rose-500 hover:text-rose-800 focus:outline-none"
            title="Dismiss error"
            aria-label="Dismiss error"
        >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

