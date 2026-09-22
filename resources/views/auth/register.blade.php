@extends('layouts.app')

@section('title', 'Criar conta')

@section('conteudo')
    <h1>Criar conta</h1>
    <p class="sub">Sua carteira é criada junto com o cadastro.</p>

    @include('layouts.erros')

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <label for="name">Nome</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus>

        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>

        <label for="password">Senha</label>
        <input id="password" name="password" type="password" required>

        <label for="password_confirmation">Confirme a senha</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required>

        <button type="submit">Criar conta</button>
    </form>

    <p class="alt">Já tem conta? <a href="{{ route('login') }}">Entrar</a></p>
@endsection
