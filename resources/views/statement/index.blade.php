@extends('layouts.app')

@section('title', 'Extrato')

@section('conteudo')
<div class="estreito">
    <div class="cabeca">
        <div>
            <h1>Extrato</h1>
            <p class="sub">Todas as movimentações da sua carteira, da mais recente para a mais antiga.</p>
        </div>
        <p class="leitura-saldo"><span>Saldo disponível</span><b>{{ $saldo }}</b></p>
    </div>

    @include('layouts.mensagem')
    @include('layouts.erros')

    <section class="cartao folha" aria-label="Lançamentos">
        @include('statement.lista', ['itens' => $pagina, 'vazio' => 'Nenhuma movimentação ainda.'])

        @if ($pagina->hasPages())
            <nav class="paginas" aria-label="Páginas do extrato">
                @if ($pagina->previousPageUrl())
                    <a href="{{ $pagina->previousPageUrl() }}" rel="prev"><span class="seta" aria-hidden="true">&larr;</span>Mais recentes</a>
                @endif

                <span class="conta">Página {{ $pagina->currentPage() }} de {{ $pagina->lastPage() }}</span>

                @if ($pagina->nextPageUrl())
                    <a href="{{ $pagina->nextPageUrl() }}" rel="next">Mais antigas<span class="seta" aria-hidden="true">&rarr;</span></a>
                @endif
            </nav>
        @endif
    </section>
</div>
@endsection
