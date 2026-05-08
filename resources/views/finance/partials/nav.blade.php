<div class="sf-card mb-6 p-2">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('finance.index', request()->query()) }}" class="{{ $page === 'dashboard' ? 'border-[#d4af37]/25 bg-[#d4af37]/12 text-white' : 'border-transparent text-[#c7d2e3] hover:border-white/10 hover:bg-white/5 hover:text-white' }} inline-flex items-center rounded-2xl border px-4 py-3 text-sm font-medium transition">
            Faturamento
        </a>
        <a href="{{ route('finance.production', request()->query()) }}" class="{{ $page === 'production' ? 'border-[#d4af37]/25 bg-[#d4af37]/12 text-white' : 'border-transparent text-[#c7d2e3] hover:border-white/10 hover:bg-white/5 hover:text-white' }} inline-flex items-center rounded-2xl border px-4 py-3 text-sm font-medium transition">
            Produção por barbeiro
        </a>
        <a href="{{ route('finance.commissions', request()->query()) }}" class="{{ $page === 'commissions' ? 'border-[#d4af37]/25 bg-[#d4af37]/12 text-white' : 'border-transparent text-[#c7d2e3] hover:border-white/10 hover:bg-white/5 hover:text-white' }} inline-flex items-center rounded-2xl border px-4 py-3 text-sm font-medium transition">
            Comissões
        </a>
        <a href="{{ route('finance.cash', request()->query()) }}" class="{{ $page === 'cash' ? 'border-[#d4af37]/25 bg-[#d4af37]/12 text-white' : 'border-transparent text-[#c7d2e3] hover:border-white/10 hover:bg-white/5 hover:text-white' }} inline-flex items-center rounded-2xl border px-4 py-3 text-sm font-medium transition">
            Caixa diário
        </a>
        <a href="{{ route('finance.report', request()->query()) }}" class="{{ $page === 'report' ? 'border-[#d4af37]/25 bg-[#d4af37]/12 text-white' : 'border-transparent text-[#c7d2e3] hover:border-white/10 hover:bg-white/5 hover:text-white' }} inline-flex items-center rounded-2xl border px-4 py-3 text-sm font-medium transition">
            Relatório
        </a>
        <a href="{{ route('finance.service-report', request()->query()) }}" class="{{ $page === 'service-report' ? 'border-[#d4af37]/25 bg-[#d4af37]/12 text-white' : 'border-transparent text-[#c7d2e3] hover:border-white/10 hover:bg-white/5 hover:text-white' }} inline-flex items-center rounded-2xl border px-4 py-3 text-sm font-medium transition">
            Serviços vendidos
        </a>
        <a href="{{ route('finance.performance', request()->query()) }}" class="{{ $page === 'performance' ? 'border-[#d4af37]/25 bg-[#d4af37]/12 text-white' : 'border-transparent text-[#c7d2e3] hover:border-white/10 hover:bg-white/5 hover:text-white' }} inline-flex items-center rounded-2xl border px-4 py-3 text-sm font-medium transition">
            Desempenho
        </a>
    </div>
</div>
