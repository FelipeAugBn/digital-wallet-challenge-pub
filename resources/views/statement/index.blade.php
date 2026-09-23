@extends('layouts.app')

@section('title', 'Extrato')

@section('conteudo')
<div class="estreito">
    <h1>Extrato</h1>
    <p class="sub">Todas as movimentações da sua carteira, da mais recente para a mais antiga.</p>

    @include('layouts.mensagem')
    @include('layouts.erros')

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
</div>
@endsection
