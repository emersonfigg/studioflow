<button {{ $attributes->merge(['type' => 'submit', 'class' => 'sf-button-primary !px-5 !py-3 !text-xs !font-semibold !uppercase !tracking-[0.18em]']) }}>
    {{ $slot }}
</button>
