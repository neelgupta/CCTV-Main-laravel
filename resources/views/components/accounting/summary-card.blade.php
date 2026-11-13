@props(['label', 'value', 'meta' => '', 'tone' => 'slate'])

@php
    $toneClasses = [
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
    ];
@endphp

<div class="flex min-h-[96px] flex-col justify-center px-5 py-4 ring-1 {{ $toneClasses[$tone] ?? $toneClasses['slate'] }}">
    <div class="text-[10px] font-bold uppercase tracking-[0.16em]">{{ $label }}</div>
    <div class="mt-1 text-2xl font-extrabold text-slate-950">{{ $value }}</div>
    @if(filled($meta))
        <div class="mt-1 text-xs text-slate-500">{{ $meta }}</div>
    @endif
</div>
