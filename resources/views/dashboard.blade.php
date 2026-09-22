@extends('layouts.app')

@section('title', 'Minha carteira')

@section('conteudo')
    <h1>Olá, {{ auth()->user()->name }}</h1>
    <p class="sub">Você está autenticado e sua carteira já existe.</p>

    <form class="sair" method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Sair</button>
    </form>
@endsection
