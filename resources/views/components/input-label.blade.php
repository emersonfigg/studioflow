@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-[#ffffff]']) }}>
    {{ $value ?? $slot }}
</label>
