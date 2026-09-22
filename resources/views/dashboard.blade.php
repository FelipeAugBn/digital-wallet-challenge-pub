@extends('layouts.app')

@section('title', 'Minha carteira')

@section('conteudo')
    <h1>Olá, {{ auth()->user()->name }}</h1>
    <p class="sub">Você está autenticado e sua carteira já existe.</p>

    @include('layouts.mensagem')

    <p class="alt"><a href="{{ route('deposits.create') }}">Depositar</a> · <a href="{{ route('transfers.create') }}">Transferir</a></p>

    <form class="sair" method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Sair</button>
    </form>
@endsection
