@props(['active'])

@php
$classes = ($active ?? false)
            ? 'sf-responsive-nav-link sf-responsive-nav-link--active font-semibold shadow-[inset_0_0_0_1px_color-mix(in_srgb,var(--brand-primary)_32%,transparent)]'
            : 'sf-responsive-nav-link focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
