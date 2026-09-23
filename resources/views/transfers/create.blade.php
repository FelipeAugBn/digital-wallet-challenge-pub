@extends('layouts.app')

@section('title', 'Transferir')

@section('conteudo')
    <div class="cabeca">
        <div>
            <h1>Transferir</h1>
            <p class="sub">Envie dinheiro para quem já usa a carteira.</p>
        </div>
    </div>

    <div class="painel-form">
        <section class="cartao" aria-label="Formulário de transferência">
            @include('layouts.erros')

            <form class="ficha" method="POST" action="{{ route('transfers.store') }}">
                @csrf

                {{-- A chave vem pronta do controller, que decide entre reaproveitar a
                     anterior e gerar outra. A view só imprime. --}}
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                <label for="recipient_email">E-mail de quem vai receber</label>
                <input id="recipient_email" name="recipient_email" type="email" autocomplete="off" placeholder="pessoa@exemplo.com" value="{{ old('recipient_email') }}" required autofocus @error('recipient_email') aria-invalid="true" @enderror>

                <label for="amount">Valor</label>
                <div class="campo-moeda">
                    <span class="prefixo" aria-hidden="true">R$</span>
                    <input id="amount" name="amount" type="text" inputmode="decimal" placeholder="1.000,50" value="{{ old('amount') }}" required @error('amount') aria-invalid="true" @enderror>
                </div>

                <button type="submit">Transferir</button>
            </form>

            <p class="alt"><a href="{{ route('dashboard') }}">Voltar para a carteira</a></p>
        </section>

        <section class="instrumento lado" aria-label="Saldo">
            <p class="rotulo">Saldo disponível</p>
            <p class="saldo">{{ $saldo }}</p>

            <ul class="notas">
                <li>Quem recebe precisa ter conta na Wallet.</li>
                <li>Só sai o que o saldo cobre; nenhuma carteira fica negativa.</li>
                <li>Você pode estornar uma transferência que enviou, e o valor volta na hora.</li>
            </ul>
        </section>
    </div>
@endsection
