<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-xl border border-rose-300/20 bg-rose-400/12 px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-rose-50 transition duration-150 hover:bg-rose-400/20 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 focus:ring-offset-[#1b335b]']) }}>
    {{ $slot }}
</button>
