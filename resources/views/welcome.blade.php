@extends('layouts.app')

@section('title', 'Wallet')

@section('capa')
    <section class="capa">
        <div class="medida capa-grade">
            <div>
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

            {{-- A vitrine é o próprio instrumento do painel, com os mesmos rótulos,
                 sinais e selos que a aplicação desenha. Os valores são de exemplo. --}}
            <div class="vitrine">
                <section class="instrumento" aria-label="Exemplo do painel">
                    <p class="rotulo">Saldo disponível <span class="exemplo">exemplo</span></p>
                    <p class="saldo">R$ 250,00</p>
                    <p class="desde">Última movimentação hoje às 09:41</p>

                    <dl class="leituras">
                        <div><dt>7 dias</dt><dd class="positivo">+R$ 250,00</dd></div>
                        <div><dt>no mês</dt><dd class="positivo">+R$ 250,00</dd></div>
                    </dl>

                    <ul class="fita-lista">
                        <li class="entrada estorno">
                            <span class="hora">hoje 09:41</span>
                            <span class="rot">Estorno de transferência enviada para Bruno</span>
                            <span class="valor entrada">+R$ 80,00</span>
                        </li>
                        <li class="saida estorno estornada">
                            <span class="hora">hoje 09:12</span>
                            <span class="rot">Transferência enviada para Bruno <em>estornada</em></span>
                            <span class="valor">-R$ 80,00</span>
                        </li>
                        <li class="entrada">
                            <span class="hora">ontem 18:05</span>
                            <span class="rot">Depósito</span>
                            <span class="valor entrada">+R$ 250,00</span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </section>
@endsection

@section('conteudo')
    <dl class="operacoes">
        <div>
            <dt>Depositar</dt>
            <dd>Uma entrada simulada de dinheiro na sua carteira, sem cobrança.</dd>
        </div>
        <div>
            <dt>Transferir</dt>
            <dd>Pelo e-mail de quem recebe. O valor sai de uma carteira e entra na outra na mesma operação.</dd>
        </div>
        <div>
            <dt>Estornar</dt>
            <dd>Vale para o que você mesmo iniciou. O valor volta e o extrato guarda as duas linhas.</dd>
        </div>
    </dl>
@endsection
