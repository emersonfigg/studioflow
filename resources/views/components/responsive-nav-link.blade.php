@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-xl border border-[#d4af37]/25 bg-[#d4af37]/12 px-4 py-3 text-start text-base font-medium text-white transition duration-150 ease-in-out'
            : 'block w-full rounded-xl border border-transparent px-4 py-3 text-start text-base font-medium text-[#c7d2e3] transition duration-150 ease-in-out hover:border-white/10 hover:bg-white/5 hover:text-white focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
