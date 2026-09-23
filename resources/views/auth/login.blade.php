@extends('layouts.app')

@section('title', 'Entrar')

@section('conteudo')
    <h1>Entrar</h1>
    <p class="sub">Acesse sua carteira.</p>

    @include('layouts.erros')

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus @error('email') aria-invalid="true" @enderror>

        <label for="password">Senha</label>
        <input id="password" name="password" type="password" required @error('password') aria-invalid="true" @enderror>

        <button type="submit">Entrar</button>
    </form>

    <p class="alt">Ainda não tem conta? <a href="{{ route('register') }}">Criar conta</a></p>
@endsection
