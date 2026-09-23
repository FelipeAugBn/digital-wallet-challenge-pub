<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Wallet')</title>
    {{-- O estilo mora aqui, inteiro, de proposito: as telas da aplicacao
         precisam abrir sem depender de nenhum passo de build. --}}
    <style>
        :root {
            color-scheme: light;

            --papel: #ffffff;
            --tinta: #10211d;
            --tinta-media: #46605a;
            --tinta-fraca: #5c716c;
            --regua: #dde5e1;
            --regua-forte: #bfcdc7;
            --realce: #f4f8f6;

            --verde: #0f6b5c;
            --verde-escuro: #0a5044;
            --verde-fundo: #e9f2ef;

            /* A banda da marca: um verde mais fechado que o das acoes, para que
               o botao continue sendo a coisa mais verde da tela. */
            --banda: #0b3f36;
            --banda-tinta: #b9d4cc;
            --banda-realce: #5fbfa8;

            --alerta: #9c3122;
            --alerta-regua: #edc7bd;
            --alerta-fundo: #fcf1ee;

            /* Estorno nao e erro: e um estado que pede atencao. Dai o ocre, e
               nao o vermelho das falhas. */
            --estorno: #7c5410;
            --estorno-regua: #e6d5ae;
            --estorno-fundo: #f8f1e1;
            --saida: #c9442d;

            /* A coluna de dinheiro tem largura fixa, como num livro-razao: e
               ela que mantem todos os valores num eixo so. A medida vem do
               maior valor que a validacao aceita, "-R$ 1.000.000,00". */
            --coluna: 9.75rem;
            --vao: 1.25rem;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--papel);
            color: var(--tinta);
            font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        :focus-visible { outline: 2px solid var(--verde); outline-offset: 2px; border-radius: 2px; }

        @media (prefers-reduced-motion: reduce) {
            *, ::before, ::after { transition-duration: .01ms !important; animation-duration: .01ms !important; }
        }

        /* Uma medida so: marca, titulo e valor comecam no mesmo eixo em todas as telas. */
        .medida { width: min(44rem, 100% - 2rem); margin-inline: auto; }

        /* ---- Banda da marca ---------------------------------------------- */

        .topo { background: var(--banda); }
        .topo-interno { display: flex; flex-wrap: wrap; align-items: center; gap: 0 1.5rem; }
        .marca {
            padding: 14px 0;
            color: #fff;
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -.02em;
            text-decoration: none;
        }
        .menu { display: flex; flex-wrap: wrap; }
        .menu a {
            margin-right: 1.375rem;
            padding: 16px 0 14px;
            border-bottom: 2px solid transparent;
            color: var(--banda-tinta);
            font-size: .9375rem;
            text-decoration: none;
            transition: color .12s ease, border-color .12s ease;
        }
        .menu a:hover { color: #fff; border-bottom-color: rgba(255, 255, 255, .35); }
        /* A tela aberta muda de cor, de peso e ganha um traco: nao e so a cor. */
        .menu a[aria-current] { color: #fff; font-weight: 600; border-bottom-color: var(--banda-realce); }
        .topo form { margin-left: auto; }
        .topo form button {
            min-height: 34px;
            padding: 6px 12px;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, .35);
            color: var(--banda-tinta);
            font-size: .875rem;
            font-weight: 500;
        }
        .topo form button:hover { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .6); color: #fff; }
        .topo :focus-visible { outline-color: var(--banda-realce); }

        /* ---- Conteudo ----------------------------------------------------- */

        main { padding: 36px 0 64px; }

        h1 { margin: 0 0 6px; font-size: 1.6rem; font-weight: 700; letter-spacing: -.02em; }
        h2 {
            margin: 40px 0 8px;
            padding-top: 14px;
            border-top: 1px solid var(--regua);
            font-size: .9375rem;
            font-weight: 600;
        }
        .sub { margin: 0 0 28px; max-width: 52ch; color: var(--tinta-media); font-size: .9375rem; }
        a { color: var(--verde); text-underline-offset: 2px; }

        /* ---- Saldo: a unica coisa grande do projeto ----------------------- */

        .rotulo { margin: 0 0 2px; color: var(--tinta-media); font-size: .875rem; }
        .saldo {
            margin: 0;
            padding-bottom: 14px;
            /* Regua dupla: como se fecha um total num livro contabil. */
            border-bottom: 4px double var(--verde);
            font-size: clamp(2.5rem, 9vw, 3.1rem);
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: -.035em;
            font-variant-numeric: tabular-nums;
        }
        .saldo.negativo { border-bottom-color: var(--alerta); color: var(--alerta); }

        .desde { margin: 12px 0 0; color: var(--tinta-fraca); font-size: .875rem; }

        .acoes { display: flex; flex-wrap: wrap; gap: 10px; margin: 24px 0 0; }
        .acoes a {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            padding: 11px 18px;
            border: 1px solid var(--verde);
            border-radius: 4px;
            color: var(--verde);
            font-size: .9375rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color .12s ease, border-color .12s ease;
        }
        .acoes a:hover { background: var(--verde-fundo); }
        .acoes a.primaria { background: var(--verde); color: #fff; }
        .acoes a.primaria:hover { background: var(--verde-escuro); border-color: var(--verde-escuro); }

        /* ---- Formularios -------------------------------------------------- */

        main > form { max-width: 22rem; }
        label { display: block; margin-bottom: 5px; font-size: .875rem; font-weight: 600; }
        input {
            width: 100%;
            margin-bottom: 18px;
            padding: 11px 12px;
            border: 1px solid var(--regua-forte);
            border-radius: 4px;
            background: var(--papel);
            color: var(--tinta);
            font: inherit;
            transition: border-color .12s ease;
        }
        input::placeholder { color: var(--tinta-fraca); }
        input:hover { border-color: var(--tinta-fraca); }
        input:focus-visible { border-color: var(--verde); outline-offset: 1px; }
        /* O campo recusado fica marcado, e o leitor de tela recebe o mesmo aviso. */
        input[aria-invalid] { border-color: var(--alerta); }
        input[aria-invalid]:focus-visible { border-color: var(--alerta); outline-color: var(--alerta); }

        /* Campo de dinheiro: a moeda fica numa casa propria, separada por regua. */
        .campo-moeda { position: relative; margin-bottom: 18px; }
        .campo-moeda input { margin-bottom: 0; padding-left: 58px; font-variant-numeric: tabular-nums; }
        .campo-moeda .prefixo {
            position: absolute;
            top: 1px;
            bottom: 1px;
            left: 1px;
            display: flex;
            width: 46px;
            align-items: center;
            justify-content: center;
            border-right: 1px solid var(--regua-forte);
            color: var(--tinta-media);
            font-size: .9375rem;
            font-weight: 600;
        }

        button {
            min-height: 44px;
            padding: 11px 18px;
            border: 1px solid var(--verde);
            border-radius: 4px;
            background: var(--verde);
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .12s ease, border-color .12s ease, color .12s ease;
        }
        button:hover { background: var(--verde-escuro); border-color: var(--verde-escuro); }
        button:disabled { background: var(--regua); border-color: var(--regua-forte); color: var(--tinta-fraca); cursor: not-allowed; }
        main > form button { width: 100%; }

        /* ---- Mensagens: a forma do sinal muda junto com a cor ------------- */

        @keyframes surge {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: none; }
        }

        .erros, .ok {
            position: relative;
            max-width: 34rem;
            animation: surge .22s ease-out;
            margin: 0 0 24px;
            padding: 13px 16px 13px 42px;
            border: 1px solid;
            border-left-width: 3px;
            border-radius: 3px;
            font-size: .9375rem;
        }
        .erros {
            list-style: none;
            background: var(--alerta-fundo);
            border-color: var(--alerta-regua);
            border-left-color: var(--alerta);
            color: var(--alerta);
        }
        .erros li + li { margin-top: 5px; }
        .erros::before {
            content: "";
            position: absolute;
            top: 17px;
            left: 16px;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-bottom: 12px solid var(--alerta);
        }
        .ok { background: var(--verde-fundo); border-color: #bcd8cf; border-left-color: var(--verde); color: var(--verde-escuro); }
        .ok::before {
            content: "";
            position: absolute;
            top: 17px;
            left: 19px;
            width: 6px;
            height: 11px;
            border-right: 2px solid var(--verde);
            border-bottom: 2px solid var(--verde);
            transform: rotate(40deg);
        }

        /* ---- Extrato: a folha de razao ------------------------------------ */

        .extrato { position: relative; margin: 0; padding: 0; list-style: none; }
        /* A regua vertical que separa o historico da coluna de dinheiro. Fica na
           lista, e nao em cada linha, para descer inteira pelos dias. */
        .extrato::after {
            content: "";
            position: absolute;
            top: 0;
            right: calc(var(--coluna) + var(--vao) / 2);
            bottom: 0;
            width: 1px;
            background: var(--regua);
        }
        .extrato li {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) var(--coluna);
            align-items: baseline;
            column-gap: var(--vao);
            padding: 15px 0;
            border-bottom: 1px solid var(--regua);
            transition: background-color .12s ease;
        }
        .extrato li:not(.dia):hover { background: var(--realce); }

        /* O dia abre o grupo, como o cabecalho de um dia num extrato de banco. */
        .extrato li.dia {
            display: block;
            padding: 24px 0 6px;
            border-bottom: 0;
            color: var(--tinta-media);
            font-size: .8125rem;
            font-weight: 600;
        }
        .extrato li.dia:first-child { padding-top: 8px; }
        .extrato .descricao { display: block; min-width: 0; font-size: .9375rem; }
        .extrato .meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 12px;
            margin-top: 5px;
            color: var(--tinta-fraca);
            font-size: .8125rem;
            font-variant-numeric: tabular-nums;
        }
        .extrato .valor {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            letter-spacing: -.01em;
            text-align: right;
            white-space: nowrap;
            color: var(--saida);
        }
        /* O sinal ja diz a direcao; a cor so reforca o que o texto afirma, e e a
           mesma dos graficos do painel: verde entrando, coral saindo. */
        .extrato .valor.entrada { color: var(--verde); }

        /* Concluida e o caso comum e fica discreta. O selo sobra para o estorno. */
        .extrato .situacao { color: var(--tinta-fraca); }
        .extrato .situacao.estornada {
            padding: 1px 8px;
            border: 1px solid var(--estorno-regua);
            border-radius: 99px;
            background: var(--estorno-fundo);
            color: var(--estorno);
            font-weight: 600;
        }
        .extrato li.estornada .descricao { color: var(--tinta-media); }
        .extrato li.estornada::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: -14px;
            width: 3px;
            background: var(--estorno-regua);
        }

        /* A acao fica do lado do historico: a coluna de dinheiro so tem dinheiro. */
        .extrato .estorno { justify-self: start; margin: 10px 0 0; }
        .extrato .estorno button {
            min-height: 36px;
            padding: 7px 14px;
            background: var(--papel);
            border-color: var(--regua-forte);
            color: var(--alerta);
            font-size: .8125rem;
        }
        .extrato .estorno button:hover { background: var(--alerta-fundo); border-color: var(--alerta-regua); color: var(--alerta); }

        .vazio { margin: 0; padding: 28px 0; border-bottom: 1px solid var(--regua); color: var(--tinta-media); font-size: .9375rem; }

        .paginas { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 20px; font-size: .9375rem; }
        .paginas a {
            display: inline-flex;
            gap: 8px;
            min-height: 40px;
            align-items: center;
            padding: 8px 14px;
            border: 1px solid var(--regua-forte);
            border-radius: 4px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color .12s ease, border-color .12s ease;
        }
        .paginas a:hover { background: var(--verde-fundo); border-color: var(--verde); }
        .paginas .seta { color: var(--tinta-fraca); }
        .paginas .conta { margin: 0 auto; color: var(--tinta-media); font-variant-numeric: tabular-nums; }

        .alt { margin: 28px 0 0; color: var(--tinta-media); font-size: .9375rem; }
        .alt a { padding: 6px 2px; }

        /* ---- Capa da pagina de entrada ------------------------------------ */

        .capa { padding: 20px 0 52px; background: var(--banda); color: #fff; }
        .capa h1 {
            max-width: 17ch;
            margin: 0 0 14px;
            font-size: clamp(1.8rem, 5.5vw, 2.6rem);
            line-height: 1.15;
            letter-spacing: -.03em;
            text-wrap: balance;
        }
        .capa .sub { max-width: 46ch; margin: 0 0 28px; color: var(--banda-tinta); font-size: 1rem; }
        .capa .acoes { margin: 0; }
        .capa .acoes a { border-color: rgba(255, 255, 255, .45); color: #fff; }
        .capa .acoes a:hover { background: rgba(255, 255, 255, .12); }
        .capa .acoes a.primaria { background: #fff; border-color: #fff; color: var(--banda); }
        .capa .acoes a.primaria:hover { background: var(--banda-tinta); border-color: var(--banda-tinta); }
        .capa :focus-visible { outline-color: var(--banda-realce); }

        .amostra { margin: 0 0 10px; color: var(--tinta-media); font-size: .875rem; }

        /* Graficos do painel: os unicos cartoes da folha, e so para os graficos. */
        .graficos { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 32px 0 0; }
        .cartao { background: var(--papel); border: 1px solid var(--regua); border-radius: 14px; padding: 18px 18px 14px; box-shadow: 0 10px 24px -18px rgba(16, 33, 29, .35); }
        .cartao.largo { grid-column: 1 / -1; }
        .cartao h2 { margin: 0; padding: 0; border: 0; font-size: .9375rem; }
        .cartao .sub { margin: 2px 0 10px; color: var(--tinta-fraca); font-size: .8125rem; }
        .cartao svg { display: block; width: 100%; height: auto; overflow: visible; }
        .cartao svg text { font-family: inherit; }
        .eixo { font-size: 11px; fill: var(--tinta-fraca); }
        .traco { fill: none; stroke: var(--verde); stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
        .marca-dia { fill: var(--papel); stroke: var(--verde); stroke-width: 2; }
        .marca-estorno { fill: var(--estorno-fundo); stroke: var(--estorno); stroke-width: 2; }
        .ponto-fim { fill: var(--verde); }
        .halo { fill: var(--verde); opacity: .18; transform-origin: center; transform-box: fill-box; animation: pulso 2.4s ease-out infinite; }
        @keyframes pulso { from { transform: scale(.6); opacity: .35; } to { transform: scale(1.6); opacity: 0; } }
        .rotulo-fim { font-size: 13px; font-weight: 700; fill: var(--tinta); }
        .eixo-meio { stroke: var(--regua-forte); stroke-width: 1; }
        .b-entrada { fill: var(--verde); }
        .b-saida { fill: var(--saida); }
        .b-estorno { fill: var(--estorno); }
        .legenda { display: flex; flex-wrap: wrap; gap: 14px; margin: 6px 0 0; padding: 0; list-style: none; color: var(--tinta-fraca); font-size: .8125rem; }
        .legenda li::before { content: ""; display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; background: var(--verde); }
        .legenda li.saida::before { background: var(--saida); }
        .legenda li.estorno::before { background: var(--estorno); }
        .anel-caixa { display: grid; grid-template-columns: 124px minmax(0, 1fr); gap: 10px; align-items: center; }
        .trilho { fill: none; stroke: var(--regua); stroke-width: 12; }
        .seg-entrou { fill: none; stroke: var(--verde); stroke-width: 12; stroke-linecap: round; }
        .seg-saiu { fill: none; stroke: var(--saida); stroke-width: 12; stroke-linecap: round; }
        .anel-percentual { font-size: 22px; font-weight: 800; fill: var(--tinta); letter-spacing: -.02em; }
        .anel-lado { font-size: 10px; fill: var(--tinta-fraca); }
        .anel-legenda { margin: 0; padding: 0; list-style: none; font-size: .8125rem; }
        .anel-legenda li { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid var(--regua); }
        .anel-legenda li:last-child { border-bottom: 0; }
        .anel-legenda b { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .anel-legenda .entrou b { color: var(--verde); }
        .anel-legenda .saiu b { color: var(--saida); }
        .anel-legenda .estornado b { color: var(--estorno); }
        .anel-legenda .liquido { margin-top: 2px; border-top: 2px solid var(--regua); }
        .anel-legenda .liquido span { font-weight: 600; }
        .sem-janela { margin: 28px 0 0; color: var(--tinta-media); font-size: .9375rem; }

        @media (prefers-reduced-motion: reduce) {
            .halo { animation: none; }
        }

        @media (max-width: 44rem) {
            .graficos { grid-template-columns: 1fr; }
            .curva .eixo { font-size: 19px; }
            .curva .rotulo-fim { font-size: 22px; }
            :root { --coluna: 9.125rem; --vao: 1rem; }
            .topo-interno { gap: 0 1rem; }
            .marca { padding: 12px 0; }
            .menu { order: 3; flex-basis: 100%; }
            .menu a { margin-right: 1.125rem; padding: 10px 0; font-size: .875rem; }
            .extrato .valor { font-size: .9375rem; }
            main { padding: 28px 0 48px; }
            .extrato li.estornada::before { left: -10px; }
        }
    </style>
</head>
<body>
    <header class="topo">
        <div class="medida topo-interno">
            <a class="marca" href="{{ Auth::check() ? route('dashboard') : url('/') }}">Wallet</a>

            @auth
                <nav class="menu" aria-label="Navegação principal">
                    <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Carteira</a>
                    <a href="{{ route('deposits.create') }}" @if (request()->routeIs('deposits.create')) aria-current="page" @endif>Depositar</a>
                    <a href="{{ route('transfers.create') }}" @if (request()->routeIs('transfers.create')) aria-current="page" @endif>Transferir</a>
                    <a href="{{ route('statement') }}" @if (request()->routeIs('statement')) aria-current="page" @endif>Extrato</a>
                </nav>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Sair</button>
                </form>
            @endauth
        </div>
    </header>

    @yield('capa')

    <main class="medida">
        @yield('conteudo')
    </main>
</body>
</html>
