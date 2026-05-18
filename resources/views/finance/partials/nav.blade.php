<div class="sf-card mb-6 p-2">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('finance.index', request()->query()) }}" class="{{ $page === 'dashboard' ? 'sf-tab sf-tab--active' : 'sf-tab' }}">
            Faturamento
        </a>
        <a href="{{ route('finance.production', request()->query()) }}" class="{{ $page === 'production' ? 'sf-tab sf-tab--active' : 'sf-tab' }}">
            Produção por profissional
        </a>
        <a href="{{ route('finance.commissions', request()->query()) }}" class="{{ $page === 'commissions' ? 'sf-tab sf-tab--active' : 'sf-tab' }}">
            Comissões
        </a>
        <a href="{{ route('finance.product-commissions', request()->query()) }}" class="{{ $page === 'product-commissions' ? 'sf-tab sf-tab--active' : 'sf-tab' }}">
            Comissões de produtos
        </a>
        <a href="{{ route('finance.cash', request()->query()) }}" class="{{ $page === 'cash' ? 'sf-tab sf-tab--active' : 'sf-tab' }}">
            Caixa diário
        </a>
        <a href="{{ route('finance.report', request()->query()) }}" class="{{ $page === 'report' ? 'sf-tab sf-tab--active' : 'sf-tab' }}">
            Relatório
        </a>
        <a href="{{ route('finance.service-report', request()->query()) }}" class="{{ $page === 'service-report' ? 'sf-tab sf-tab--active' : 'sf-tab' }}">
            Serviços vendidos
        </a>
        <a href="{{ route('finance.performance', request()->query()) }}" class="{{ $page === 'performance' ? 'sf-tab sf-tab--active' : 'sf-tab' }}">
            Desempenho
        </a>
    </div>
</div>
