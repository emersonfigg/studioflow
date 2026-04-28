<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center rounded-xl border border-white/8 bg-[#132746] px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-white shadow-sm transition duration-150 hover:border-[#d4af37]/35 hover:bg-[#193056] focus:outline-none focus:ring-2 focus:ring-[#d4af37] focus:ring-offset-2 focus:ring-offset-[#1b335b] disabled:opacity-25']) }}>
    {{ $slot }}
</button>
