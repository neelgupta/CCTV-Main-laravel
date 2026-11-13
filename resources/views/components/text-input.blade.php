@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'app-input h-[42px]']) }}>
