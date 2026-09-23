@extends('layouts.app')

@section('title', 'Wallet')

@section('capa')
    <section class="capa">
        <div class="medida">
            <h1>Movimente dinheiro e veja cada lançamento.</h1>

            <p class="sub">Deposite na sua carteira, transfira para quem também usa a Wallet e estorne
                uma operação que você mesmo iniciou. Todo movimento vira um lançamento no extrato.</p>

            <div class="acoes">
                @guest
                    <a class="primaria" href="{{ route('register') }}">Criar conta</a>
                    <a href="{{ route('login') }}">Entrar</a>
                @else
                    <a class="primaria" href="{{ route('dashboard') }}">Abrir minha carteira</a>
                @endguest
            </div>
        </div>
    </section>
@endsection

@section('conteudo')
    {{-- Uma amostra do extrato real: os mesmos rótulos, sinais e selos que a
         aplicação desenha. Os valores são de exemplo. --}}
    <p class="amostra">Exemplo de extrato</p>

    <ul class="extrato">
        <li>
            <span class="descricao">
                Estorno de transferência enviada para Bruno
                <span class="meta">
                    <span>14/03/2026 09:41</span>
                    <span class="situacao">Concluída</span>
                </span>
            </span>
            <span class="valor entrada">+R$ 80,00</span>
        </li>
        <li class="estornada">
            <span class="descricao">
                Transferência enviada para Bruno
                <span class="meta">
                    <span>14/03/2026 09:12</span>
                    <span class="situacao estornada">Estornada</span>
                </span>
            </span>
            <span class="valor">-R$ 80,00</span>
        </li>
        <li>
            <span class="descricao">
                Depósito
                <span class="meta">
                    <span>13/03/2026 18:05</span>
                    <span class="situacao">Concluída</span>
                </span>
            </span>
            <span class="valor entrada">+R$ 250,00</span>
        </li>
    </ul>
@endsection
