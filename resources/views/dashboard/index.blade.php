@extends('layouts.app')

@section('title', 'Minha carteira')
@section('largura', 'largo')

@section('conteudo')
    <h1>Olá, {{ $nome }}</h1>

    @include('layouts.mensagem')

    <p class="rotulo">Saldo disponível</p>
    <p class="saldo @if (str_starts_with($saldo, '-')) negativo @endif">{{ $saldo }}</p>

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
