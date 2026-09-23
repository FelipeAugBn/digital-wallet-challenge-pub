@extends('layouts.app')

@use('Illuminate\Support\Str')

@section('title', 'Minha carteira')

@section('conteudo')
    @include('layouts.mensagem')

    <h1 class="ola">Olá, {{ $nome }}</h1>

    <div class="grade">
        {{-- O instrumento: o saldo oficial, as leituras da semana e do mês, e as ações. --}}
        <section class="instrumento" aria-label="Saldo">
            <p class="rotulo">Saldo disponível</p>
            <p @class(['saldo', 'negativo' => str_starts_with($saldo, '-')])>{{ $saldo }}</p>

            @if ($recentes->isNotEmpty())
                <p class="desde">Última movimentação {{ Str::lcfirst($recentes->first()->dayLabel) }} às {{ $recentes->first()->time }}</p>
            @endif

            @if ($graficos)
                <dl class="leituras">
                    <div><dt>7 dias</dt><dd class="{{ $graficos->seteDias['classe'] }}">{{ $graficos->seteDias['valor'] }}</dd></div>
                    <div><dt>{{ Str::lower($graficos->mes) }}</dt><dd class="{{ $graficos->liquidoMes['classe'] }}">{{ $graficos->liquidoMes['valor'] }}</dd></div>
                </dl>
            @elseif ($recentes->isNotEmpty())
                <p class="sem-janela">Sem movimentação nas últimas cinco semanas.</p>
            @endif

            <div class="acoes">
                <a class="primaria" href="{{ route('deposits.create') }}">Depositar</a>
                <a href="{{ route('transfers.create') }}">Transferir</a>
                <a href="{{ route('statement') }}">Ver extrato</a>
            </div>
        </section>

        @if ($graficos)
            @include('dashboard.graficos')
        @endif

        <section class="cartao fita">
            <h2>Movimentações recentes</h2>

            @include('dashboard.fita', ['itens' => $recentes, 'vazio' => 'Nenhuma movimentação ainda.'])

            @if ($recentes->isNotEmpty())
                <p class="alt"><a href="{{ route('statement') }}">Ver o extrato completo</a></p>
            @endif
        </section>
    </div>
@endsection
