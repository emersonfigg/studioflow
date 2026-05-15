@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium leading-5 transition duration-150 ease-in-out border-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,var(--card-bg))] text-[var(--text-main)]'
            : 'inline-flex items-center rounded-xl border border-transparent px-4 py-2 text-sm font-medium leading-5 sf-text-muted transition duration-150 ease-in-out hover:border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] hover:bg-[color-mix(in_srgb,var(--text-main)_5%,transparent)] hover:text-[var(--text-main)] focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
