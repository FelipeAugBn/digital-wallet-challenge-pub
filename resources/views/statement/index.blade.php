@extends('layouts.app')

@section('title', 'Extrato')
@section('largura', 'largo')

@section('conteudo')
    <h1>Extrato</h1>
    <p class="sub">Todas as movimentações da sua carteira, da mais recente para a mais antiga.</p>

    @include('statement.lista', ['itens' => $pagina, 'vazio' => 'Nenhuma movimentação ainda.'])

    @if ($pagina->hasPages())
        <nav class="paginas" aria-label="Páginas do extrato">
            @if ($pagina->previousPageUrl())
                <a href="{{ $pagina->previousPageUrl() }}" rel="prev">← Mais recentes</a>
            @endif

            <span class="conta">Página {{ $pagina->currentPage() }} de {{ $pagina->lastPage() }}</span>

            @if ($pagina->nextPageUrl())
                <a href="{{ $pagina->nextPageUrl() }}" rel="next">Mais antigas →</a>
            @endif
        </nav>
    @endif
@endsection
