@extends('layouts.app')

@use('Illuminate\Support\Str')

@section('title', 'Minha carteira')

@section('conteudo')
    @include('layouts.mensagem')

    <h1>Olá, {{ $nome }}</h1>

    <p class="rotulo">Saldo disponível</p>
    <p @class(['saldo', 'negativo' => str_starts_with($saldo, '-')])>{{ $saldo }}</p>

    @if ($recentes->isNotEmpty())
        <p class="desde">Última movimentação {{ Str::lcfirst($recentes->first()->dayLabel) }} às {{ $recentes->first()->time }}</p>
    @endif

    <div class="acoes">
        <a class="primaria" href="{{ route('deposits.create') }}">Depositar</a>
        <a href="{{ route('transfers.create') }}">Transferir</a>
        <a href="{{ route('statement') }}">Ver extrato</a>
    </div>

    <h2>Movimentações recentes</h2>

    @include('statement.lista', ['itens' => $recentes, 'vazio' => 'Nenhuma movimentação ainda.'])

    @if ($recentes->isNotEmpty())
        <p class="alt"><a href="{{ route('statement') }}">Ver o extrato completo</a></p>
    @endif
@endsection
