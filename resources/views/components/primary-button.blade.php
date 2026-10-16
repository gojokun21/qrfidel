<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-dark']) }}>
    {{ $slot }}
</button>
