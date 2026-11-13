<button {{ $attributes->merge(['type' => 'submit', 'class' => 'app-button app-button-primary uppercase tracking-widest']) }}>
    {{ $slot }}
</button>
