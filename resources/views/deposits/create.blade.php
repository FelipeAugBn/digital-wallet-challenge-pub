@extends('layouts.app')

@section('title', 'Depositar')

@section('conteudo')
    <div class="cabeca">
        <div>
            <h1>Depositar</h1>
            <p class="sub">Uma entrada simulada de dinheiro na sua carteira.</p>
        </div>
    </div>

    <div class="painel-form">
        <section class="cartao" aria-label="Formulário de depósito">
            @include('layouts.erros')

            <form class="ficha" method="POST" action="{{ route('deposits.store') }}">
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
        </section>

        <section class="instrumento lado" aria-label="Saldo">
            <p class="rotulo">Saldo disponível</p>
            <p class="saldo">{{ $saldo }}</p>

            <ul class="notas">
                <li>Até R$ 1.000.000,00 por depósito, com centavos depois da vírgula.</li>
                <li>O valor entra na hora e aparece no extrato.</li>
                <li>Um depósito pode ser estornado depois, pelo extrato.</li>
            </ul>
        </section>
    </div>
@endsection
