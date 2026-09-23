@extends('layouts.app')

@section('title', 'Depositar')

@section('conteudo')
    <h1>Depositar</h1>
    <p class="sub">Uma entrada simulada de dinheiro na sua carteira.</p>

    @include('layouts.erros')

    <form method="POST" action="{{ route('deposits.store') }}">
        @csrf

        {{-- A chave vem do servidor. No erro de validação ela é a mesma, para
             que reenviar o formulário corrigido continue sendo a mesma operação. --}}
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

        <label for="amount">Valor</label>
        <div class="campo-moeda">
            <span class="prefixo" aria-hidden="true">R$</span>
            <input id="amount" name="amount" type="text" inputmode="decimal" placeholder="1.000,50" value="{{ old('amount') }}" required autofocus @error('amount') aria-invalid="true" @enderror>
        </div>

        <button type="submit">Depositar</button>
    </form>

    <p class="alt"><a href="{{ route('dashboard') }}">Voltar para a carteira</a></p>
@endsection
