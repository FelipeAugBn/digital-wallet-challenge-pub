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

    {{-- O filtro é um formulário GET: a busca vira endereço, dá para guardar e
         compartilhar, e as outras páginas carregam o mesmo período. --}}
    <form class="filtro" method="GET" action="{{ route('statement') }}" aria-label="Filtrar por período">
        <div>
            <label for="inicio">Data inicial</label>
            <input id="inicio" name="inicio" type="date" value="{{ old('inicio', $filtros['inicio'] ?? '') }}" @error('inicio') aria-invalid="true" @enderror>
        </div>

        <div>
            <label for="fim">Data final</label>
            <input id="fim" name="fim" type="date" value="{{ old('fim', $filtros['fim'] ?? '') }}" @error('fim') aria-invalid="true" @enderror>
        </div>

        <button type="submit">Filtrar</button>
        <a class="limpar" href="{{ route('statement') }}">Limpar</a>
    </form>

    <section class="cartao folha" aria-label="Lançamentos">
        @include('statement.lista', ['itens' => $pagina, 'vazio' => $vazio])

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
