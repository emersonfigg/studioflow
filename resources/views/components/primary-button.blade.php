<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-xl border border-[#d4af37]/30 bg-[#d4af37] px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-[#132746] shadow-[0_10px_24px_rgba(212,175,55,0.22)] transition duration-150 hover:bg-[#e0bd54] focus:outline-none focus:ring-2 focus:ring-[#d4af37] focus:ring-offset-2 focus:ring-offset-[#1b335b]']) }}>
    {{ $slot }}
</button>
