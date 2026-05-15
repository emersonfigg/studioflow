<button {{ $attributes->merge(['type' => 'button', 'class' => 'sf-button-secondary !px-5 !py-3 !text-xs !font-semibold !uppercase !tracking-[0.18em] disabled:opacity-25']) }}>
    {{ $slot }}
</button>
