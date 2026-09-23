<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Wallet')</title>
    {{-- O tema e escolha da pessoa e fica so no navegador dela. Este trecho roda
         antes do primeiro desenho para a pagina nao piscar clara antes de
         escurecer; o padrao, sem escolha guardada, e o claro. --}}
    <script>try{if(localStorage.getItem('wallet-tema')==='dark'){document.documentElement.setAttribute('data-theme','dark')}}catch(e){}</script>
    {{-- O estilo mora aqui, inteiro, de proposito: as telas da aplicacao
         precisam abrir sem depender de nenhum passo de build. --}}
    <style>
        :root {
            color-scheme: light;

            --fundo: #f4f7f5;
            --papel: #ffffff;
            --papel-2: #f1f5f3;
            --tinta: #10211d;
            --tinta-media: #46605a;
            --tinta-fraca: #5c716c;
            --regua: #dde5e1;
            --regua-forte: #bfcdc7;
            --realce: #f4f8f6;

            --verde: #0f6b5c;
            --verde-escuro: #0a5044;
            --verde-fundo: #e9f2ef;
            --sobre-verde: #ffffff;

            --alerta: #9c3122;
            --alerta-regua: #edc7bd;
            --alerta-fundo: #fcf1ee;

            /* Tres cores dizem a direcao do dinheiro, na tela e nos graficos:
               verde entrando, coral saindo, ambar quando houve estorno. Estorno
               nao e erro, dai nao ser o vermelho das falhas. */
            --saida: #c43f2a;
            --estorno: #7c5410;
            --estorno-regua: #e6d5ae;
            --estorno-fundo: #f8f1e1;

            --sombra: 0 1px 2px rgba(16, 33, 29, .04);

            /* Todo numero em mono: e como se le um extrato, e os digitos se
               alinham sem esforco. */
            --mono: ui-monospace, "Cascadia Mono", "SF Mono", Menlo, Consolas, "DejaVu Sans Mono", monospace;

            /* A coluna de dinheiro do extrato tem largura fixa, como num
               livro-razao. A medida vem do maior valor que a validacao aceita,
               "-R$ 1.000.000,00". */
            --coluna: 9.75rem;
            --vao: 1.25rem;
        }

        :root[data-theme="dark"] {
            color-scheme: dark;

            --fundo: #0b191e;
            --papel: #102227;
            --papel-2: #15292f;
            --tinta: #e8f3f1;
            --tinta-media: #a9c4c0;
            --tinta-fraca: #8aa8a2;
            --regua: #1c343b;
            --regua-forte: #2a4850;
            --realce: #15292f;

            --verde: #45c9a3;
            --verde-escuro: #6ad6b7;
            --verde-fundo: rgba(69, 201, 163, .12);
            --sobre-verde: #06231b;

            --alerta: #f08c7c;
            --alerta-regua: rgba(240, 140, 124, .4);
            --alerta-fundo: rgba(240, 140, 124, .1);

            --saida: #e5735f;
            --estorno: #d6a545;
            --estorno-regua: rgba(214, 165, 69, .45);
            --estorno-fundo: rgba(214, 165, 69, .12);

            --sombra: none;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--fundo);
            color: var(--tinta);
            font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        :focus-visible { outline: 2px solid var(--verde); outline-offset: 2px; border-radius: 2px; }

        @media (prefers-reduced-motion: reduce) {
            *, ::before, ::after { transition-duration: .01ms !important; animation-duration: .01ms !important; }
        }

        .medida { width: min(70rem, 100% - 2.5rem); margin-inline: auto; }
        /* Telas de leitura e formularios nao precisam da largura do painel. */
        .estreito { max-width: 52rem; }

        /* ---- Topo ---------------------------------------------------------- */

        .topo { background: var(--papel); border-bottom: 1px solid var(--regua); }
        .topo-interno { display: flex; flex-wrap: wrap; align-items: center; gap: 0 1.75rem; min-height: 58px; }
        .marca {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 0;
            color: var(--tinta);
            font-size: 1.0625rem;
            font-weight: 700;
            letter-spacing: -.01em;
            text-decoration: none;
        }
        .marca::before { content: ""; width: 10px; height: 10px; border-radius: 2px; background: var(--verde); }
        .menu { display: flex; flex-wrap: wrap; }
        .menu a {
            margin-right: 1.375rem;
            padding: 19px 0 17px;
            border-bottom: 1px solid transparent;
            color: var(--tinta-media);
            font-size: .9375rem;
            text-decoration: none;
            transition: color .12s ease, border-color .12s ease;
        }
        .menu a:hover { color: var(--tinta); }
        /* A tela aberta muda de cor e ganha um traco: nao e so a cor. */
        .menu a[aria-current] { color: var(--tinta); font-weight: 600; border-bottom-color: var(--verde); }
        .direita { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .tema {
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            min-height: 0;
            padding: 0;
            background: transparent;
            border: 1px solid var(--regua-forte);
            color: var(--tinta-media);
        }
        .tema:hover { background: transparent; border-color: var(--verde); color: var(--tinta); }
        .tema svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
        .tema .sol { display: none; }
        :root[data-theme="dark"] .tema .lua { display: none; }
        :root[data-theme="dark"] .tema .sol { display: block; }
        .topo form button {
            min-height: 34px;
            padding: 6px 12px;
            background: transparent;
            border: 1px solid var(--regua-forte);
            color: var(--tinta-media);
            font-size: .875rem;
            font-weight: 500;
        }
        .topo form button:hover { background: transparent; border-color: var(--verde); color: var(--tinta); }

        /* ---- Conteudo ------------------------------------------------------ */

        main { padding: 32px 0 64px; }

        h1 { margin: 0 0 6px; font-size: 1.5rem; font-weight: 700; letter-spacing: -.02em; }
        h1.ola { margin: 0 0 18px; color: var(--tinta-media); font-size: .9375rem; font-weight: 500; letter-spacing: 0; }
        h2 {
            margin: 40px 0 8px;
            padding-top: 14px;
            border-top: 1px solid var(--regua);
            font-size: .9375rem;
            font-weight: 600;
        }
        .sub { margin: 0 0 24px; max-width: 52ch; color: var(--tinta-media); font-size: .9375rem; }
        a { color: var(--verde); text-underline-offset: 2px; }

        /* ---- Cartoes: a grade do painel ------------------------------------- */

        .grade { display: grid; grid-template-columns: 7fr 5fr; gap: 18px; }
        .cartao {
            background: var(--papel);
            border: 1px solid var(--regua);
            border-radius: 6px;
            padding: 20px 22px 16px;
            box-shadow: var(--sombra);
        }
        .cartao h2 { margin: 0; padding: 0; border: 0; font-size: .9375rem; }
        .cartao h2 span { margin-left: 8px; color: var(--tinta-fraca); font-weight: 400; }
        .cartao .sub { margin: 2px 0 14px; font-size: .8125rem; color: var(--tinta-fraca); }
        .cartao svg { display: block; width: 100%; height: auto; overflow: visible; }
        .largo { grid-column: 1 / -1; }
        .instrumento { order: 1; }
        .dial-caixa { order: 2; }
        .curva-caixa { order: 3; }
        .fita { order: 4; }
        .barras-caixa { order: 5; }

        /* ---- O instrumento: saldo, leituras e acoes -------------------------- */

        .instrumento { background: var(--papel); border: 1px solid var(--regua); padding: 26px 28px 24px; box-shadow: var(--sombra); }
        .rotulo { margin: 0; color: var(--tinta-fraca); font-size: .8125rem; }
        .saldo {
            margin: 4px 0 0;
            font: 500 clamp(2.5rem, 6vw, 4rem)/1 var(--mono);
            letter-spacing: -.04em;
            color: var(--tinta);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .saldo.negativo { color: var(--alerta); }
        .desde { margin: 10px 0 0; color: var(--tinta-fraca); font-size: .8125rem; }
        .leituras {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 22px;
            margin: 16px 0 0;
            padding: 12px 0 0;
            border-top: 1px dashed var(--regua-forte);
            font: .8125rem var(--mono);
        }
        .leituras div { display: flex; gap: 8px; }
        .leituras dt { color: var(--tinta-fraca); }
        .leituras dd { margin: 0; font-weight: 500; font-variant-numeric: tabular-nums; }
        .leituras .positivo { color: var(--verde); }
        .leituras .negativo { color: var(--saida); }
        .sem-janela { margin: 16px 0 0; padding: 12px 0 0; border-top: 1px dashed var(--regua-forte); color: var(--tinta-media); font-size: .875rem; }

        .acoes { display: flex; flex-wrap: wrap; gap: 10px; margin: 22px 0 0; }
        .acoes a {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            padding: 10px 18px;
            border: 1px solid var(--regua-forte);
            border-radius: 4px;
            background: var(--papel-2);
            color: var(--tinta);
            font-size: .9375rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color .12s ease, border-color .12s ease;
        }
        .acoes a:hover { border-color: var(--verde); }
        .acoes a.primaria { background: var(--verde); border-color: var(--verde); color: var(--sobre-verde); }
        .acoes a.primaria:hover { background: var(--verde-escuro); border-color: var(--verde-escuro); }

        /* ---- Formularios ---------------------------------------------------- */

        .ficha { max-width: 22rem; }
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
        .campo-moeda input { margin-bottom: 0; padding-left: 58px; font-family: var(--mono); font-variant-numeric: tabular-nums; }
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
            font: 600 .9375rem var(--mono);
        }

        button {
            min-height: 42px;
            padding: 10px 18px;
            border: 1px solid var(--verde);
            border-radius: 4px;
            background: var(--verde);
            color: var(--sobre-verde);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .12s ease, border-color .12s ease, color .12s ease;
        }
        button:hover { background: var(--verde-escuro); border-color: var(--verde-escuro); }
        button:disabled { background: var(--regua); border-color: var(--regua-forte); color: var(--tinta-fraca); cursor: not-allowed; }
        .ficha button[type="submit"] { width: 100%; }

        /* ---- Mensagens: a forma do sinal muda junto com a cor --------------- */

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
        .ok { background: var(--verde-fundo); border-color: var(--regua-forte); border-left-color: var(--verde); color: var(--verde); }
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

        /* ---- A fita do painel ----------------------------------------------- */

        .fita { padding-bottom: 8px; }
        .fita-lista { list-style: none; margin: 10px 0 0; padding: 0; }
        .fita-lista li {
            position: relative;
            display: grid;
            grid-template-columns: 8.5rem minmax(0, 1fr) auto auto;
            gap: 14px;
            align-items: baseline;
            padding: 10px 0 10px 12px;
            border-bottom: 1px solid var(--regua);
        }
        .fita-lista li:last-child { border-bottom: 0; }
        /* A haste a esquerda diz a direcao antes mesmo de ler o sinal. */
        .fita-lista li::before { content: ""; position: absolute; left: 0; top: 12px; bottom: 12px; width: 2px; background: var(--verde); }
        .fita-lista li.saida::before { background: var(--saida); }
        .fita-lista li.estorno::before { background: var(--estorno); }
        .fita-lista .hora { font: .75rem var(--mono); color: var(--tinta-fraca); white-space: nowrap; }
        .fita-lista .rot { min-width: 0; font-size: .9375rem; }
        .fita-lista .rot em {
            display: inline-block;
            margin-left: 8px;
            padding: 1px 6px;
            border: 1px solid var(--estorno-regua);
            border-radius: 3px;
            background: var(--estorno-fundo);
            color: var(--estorno);
            font: .6875rem var(--mono);
        }
        .fita-lista li.estornada .rot { color: var(--tinta-fraca); text-decoration: line-through; text-decoration-color: var(--estorno); }
        .fita-lista li.estornada .rot em { text-decoration: none; }
        .fita-lista .valor { font: 500 .875rem var(--mono); color: var(--saida); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .fita-lista .valor.entrada { color: var(--verde); }
        .fita-lista .pos { font: .75rem var(--mono); color: var(--tinta-fraca); white-space: nowrap; }
        .fita-lista .pos-rotulo { margin-right: 4px; }
        .fita .alt { margin-top: 12px; }

        /* ---- Extrato: a folha de razao -------------------------------------- */

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
        .extrato .meta > span:first-child { font-family: var(--mono); }
        .extrato .valor {
            font: 500 .9375rem var(--mono);
            font-variant-numeric: tabular-nums;
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
            border-radius: 3px;
            background: var(--estorno-fundo);
            color: var(--estorno);
            font: .6875rem var(--mono);
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
            min-height: 34px;
            padding: 6px 14px;
            background: var(--papel);
            border-color: var(--regua-forte);
            color: var(--alerta);
            font-size: .8125rem;
        }
        .extrato .estorno button:hover { background: var(--alerta-fundo); border-color: var(--alerta-regua); color: var(--alerta); }

        .vazio { margin: 0; padding: 24px 0; color: var(--tinta-media); font-size: .9375rem; }

        .paginas { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 20px; font-size: .9375rem; }
        .paginas a {
            display: inline-flex;
            gap: 8px;
            min-height: 40px;
            align-items: center;
            padding: 8px 14px;
            border: 1px solid var(--regua-forte);
            border-radius: 4px;
            background: var(--papel);
            font-weight: 600;
            text-decoration: none;
            transition: background-color .12s ease, border-color .12s ease;
        }
        .paginas a:hover { background: var(--verde-fundo); border-color: var(--verde); }
        .paginas .seta { color: var(--tinta-fraca); }
        .paginas .conta { margin: 0 auto; color: var(--tinta-media); font: .875rem var(--mono); }

        .alt { margin: 28px 0 0; color: var(--tinta-media); font-size: .9375rem; }
        .alt a { padding: 6px 2px; }

        /* ---- Pagina inicial: a vitrine e o proprio instrumento ---------------- */

        .capa { padding: 40px 0 8px; }
        .capa-grade { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 36px; align-items: center; }
        .capa h1 {
            max-width: 17ch;
            margin: 0 0 14px;
            font-size: clamp(1.8rem, 5vw, 2.6rem);
            line-height: 1.15;
            letter-spacing: -.03em;
            text-wrap: balance;
        }
        .capa .sub { max-width: 46ch; margin: 0 0 28px; font-size: 1rem; }
        .capa .acoes { margin: 0; }
        /* A amostra usa as mesmas pecas do painel, com valores de exemplo. */
        .vitrine .instrumento { padding: 22px 24px 6px; }
        .vitrine .rotulo .exemplo {
            margin-left: 8px;
            padding: 1px 6px;
            border: 1px solid var(--regua-forte);
            border-radius: 3px;
            color: var(--tinta-fraca);
            font: .6875rem var(--mono);
        }
        .vitrine .saldo { font-size: clamp(2rem, 3.5vw, 2.75rem); }
        .vitrine .fita-lista { margin-top: 14px; padding-top: 4px; border-top: 1px dashed var(--regua-forte); }
        .vitrine .fita-lista li { grid-template-columns: 5.5rem minmax(0, 1fr) auto; padding: 9px 0 9px 12px; }
        .vitrine .fita-lista .pos { display: none; }
        /* As tres operacoes, lado a lado: nao e uma sequencia, dai sem numeros. */
        .operacoes {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            margin: 0;
            padding-top: 24px;
            border-top: 1px solid var(--regua);
        }
        .operacoes dt { font-size: .9375rem; font-weight: 600; }
        .operacoes dd { max-width: 34ch; margin: 4px 0 0; color: var(--tinta-media); font-size: .9375rem; }

        /* ---- Telas de acao e extrato: cabeca com a leitura do saldo ------------ */

        .cabeca {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 10px 24px;
            margin-bottom: 22px;
        }
        .cabeca .sub { margin: 0; }
        .leitura-saldo { display: flex; flex-direction: column; gap: 1px; margin: 0; text-align: right; }
        .leitura-saldo span { color: var(--tinta-fraca); font-size: .8125rem; }
        .leitura-saldo b {
            font: 500 1.25rem var(--mono);
            font-variant-numeric: tabular-nums;
            letter-spacing: -.02em;
            white-space: nowrap;
        }

        /* Depositar e transferir: a ficha num cartao, o saldo ao lado, como no painel. */
        .painel-form { display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); gap: 18px; align-items: start; }
        .painel-form .cartao { padding: 24px 26px 22px; }
        .painel-form .ficha { max-width: 26rem; }
        .instrumento.lado { padding: 22px 24px 20px; }
        .lado .saldo { font-size: clamp(1.75rem, 3vw, 2.25rem); }
        .notas {
            margin: 16px 0 0;
            padding: 12px 0 0;
            border-top: 1px dashed var(--regua-forte);
            list-style: none;
            color: var(--tinta-media);
            font-size: .875rem;
        }
        .notas li { position: relative; padding-left: 14px; }
        .notas li + li { margin-top: 6px; }
        .notas li::before { content: ""; position: absolute; top: .7em; left: 0; width: 6px; height: 1px; background: var(--regua-forte); }

        /* O extrato e uma folha: a lista e as paginas dentro do mesmo cartao. */
        .folha { padding: 6px 22px 10px; }
        .folha .extrato li.dia:first-child { padding-top: 14px; }
        .folha .extrato li:last-child { border-bottom: 0; }
        .folha .paginas { margin: 0; padding: 14px 0 6px; border-top: 1px solid var(--regua); }

        /* Entrar e criar conta: um cartao so, no centro. */
        .acesso { max-width: 26rem; margin: 16px auto 0; padding: 28px 28px 26px; }
        .acesso h1 { margin-bottom: 4px; }
        .acesso .sub { margin-bottom: 20px; }
        .acesso .ficha { max-width: none; }
        .acesso .alt { margin-top: 22px; }

        /* ---- Graficos do painel --------------------------------------------- */

        .cartao svg text { font-family: var(--mono); }
        .eixo { font-size: 11px; fill: var(--tinta-fraca); }
        .eixo.hoje { fill: var(--verde); }
        .curva .grade { stroke: var(--regua); stroke-width: 1; }
        .grade-forte { stroke: var(--regua-forte); stroke-width: 1; }
        .area-topo, .area-base { stop-color: var(--verde); }
        .traco { fill: none; stroke: var(--verde); stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
        .marca-dia { fill: var(--papel); stroke: var(--verde); stroke-width: 1.6; }
        .marca-estorno { fill: var(--papel); stroke: var(--estorno); stroke-width: 1.6; }
        .ponto-fim { fill: var(--verde); }
        .halo { fill: var(--verde); opacity: .14; }
        .rotulo-fim { font-size: 13px; font-weight: 700; fill: var(--tinta); }
        .eixo-meio { stroke: var(--regua-forte); stroke-width: 1; stroke-dasharray: 2 3; }
        .b-entrada { fill: var(--verde); }
        .b-saida { fill: var(--saida); }
        .b-estorno { fill: var(--estorno); }
        .legenda { display: flex; flex-wrap: wrap; gap: 14px; margin: 8px 0 0; padding: 0; list-style: none; color: var(--tinta-fraca); font-size: .75rem; }
        .legenda li::before { content: ""; display: inline-block; width: 7px; height: 7px; margin-right: 6px; border-radius: 50%; background: var(--verde); }
        .legenda li.saida::before { background: var(--saida); }
        .legenda li.estorno::before { background: var(--estorno); }

        .dial-caixa { display: grid; grid-template-columns: 172px minmax(0, 1fr); gap: 18px; align-items: center; }
        .dial-caixa h2 { grid-column: 1 / -1; }
        .traco-dial { stroke: var(--regua-forte); stroke-width: 1.2; }
        .trilho { fill: none; stroke: var(--regua); stroke-width: 10; }
        .seg-entrou { fill: none; stroke: var(--verde); stroke-width: 10; stroke-linecap: round; }
        .seg-saiu { fill: none; stroke: var(--saida); stroke-width: 10; stroke-linecap: round; }
        .anel-percentual { font-size: 34px; font-weight: 500; fill: var(--tinta); letter-spacing: -.04em; }
        .anel-lado { font: 11px system-ui, sans-serif; fill: var(--tinta-fraca); }
        .anel-legenda { margin: 0; padding: 0; list-style: none; font-size: .8125rem; }
        .anel-legenda li { display: flex; justify-content: space-between; gap: 10px; padding: 7px 0; border-bottom: 1px solid var(--regua); }
        .anel-legenda li:last-child { border-bottom: 0; }
        .anel-legenda span { color: var(--tinta-media); }
        .anel-legenda b { font: 500 .8125rem var(--mono); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .anel-legenda .entrou b { color: var(--verde); }
        .anel-legenda .saiu b { color: var(--saida); }
        .anel-legenda .estornado b { color: var(--estorno); }
        .anel-legenda .liquido { margin-top: 4px; border-top: 1px dashed var(--regua-forte); }
        .anel-legenda .liquido span { color: var(--tinta); font-weight: 600; }
        .anel-legenda .liquido b { font-weight: 600; }

        @media (max-width: 48rem) {
            .grade, .painel-form, .capa-grade, .operacoes { grid-template-columns: 1fr; }
            .capa { padding-top: 28px; }
            .capa-grade { gap: 24px; }
            .operacoes { gap: 16px; }
            .instrumento { padding: 22px 20px 20px; }
            .vitrine .instrumento { padding-bottom: 6px; }
            .vitrine .fita-lista li { grid-template-columns: minmax(0, 1fr) auto; }
            .painel-form .cartao, .acesso { padding: 22px 20px 20px; }
            .leitura-saldo { text-align: left; }
            .folha { padding: 4px 16px 8px; }
            .dial-caixa { grid-template-columns: 140px minmax(0, 1fr); }
            .curva .eixo { font-size: 19px; }
            .curva .rotulo-fim { font-size: 22px; }
            .fita-lista li { grid-template-columns: minmax(0, 1fr) auto; }
            .fita-lista .hora { grid-column: 1 / -1; order: -1; }
            .fita-lista .pos { display: none; }
            :root { --coluna: 9.125rem; --vao: 1rem; }
            .topo-interno { gap: 0 1rem; min-height: 0; }
            .marca { padding: 12px 0; }
            .menu { order: 3; flex-basis: 100%; }
            .menu a { margin-right: 1.125rem; padding: 10px 0 12px; font-size: .875rem; }
            .extrato .valor { font-size: .875rem; }
            main { padding: 24px 0 48px; }
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
            @endauth

            <div class="direita">
                {{-- Lua no tema claro, sol no escuro: o icone mostra para onde o botao leva. --}}
                <button class="tema" type="button" id="tema" aria-pressed="false" aria-label="Ativar tema escuro" title="Tema escuro">
                    <svg class="lua" viewBox="0 0 20 20" aria-hidden="true"><path d="M12.5 2.5a7.5 7.5 0 1 0 5 13.1 6.5 6.5 0 0 1-5-13.1z"/></svg>
                    <svg class="sol" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="3.6"/><path d="M10 1.5v2.4M10 16.1v2.4M1.5 10h2.4M16.1 10h2.4M4 4l1.7 1.7M14.3 14.3 16 16M4 16l1.7-1.7M14.3 5.7 16 4"/></svg>
                </button>

                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Sair</button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    @yield('capa')

    <main class="medida">
        @yield('conteudo')
    </main>

    {{-- A troca de tema e a unica coisa que precisa de script: o resto da
         aplicacao continua funcionando com ele desligado. --}}
    <script>
        (function () {
            var raiz = document.documentElement, botao = document.getElementById('tema');
            if (!botao) return;
            function rotula() {
                var escuro = raiz.getAttribute('data-theme') === 'dark';
                botao.setAttribute('aria-pressed', escuro ? 'true' : 'false');
                botao.setAttribute('aria-label', escuro ? 'Ativar tema claro' : 'Ativar tema escuro');
                botao.title = escuro ? 'Tema claro' : 'Tema escuro';
            }
            botao.addEventListener('click', function () {
                var escuro = raiz.getAttribute('data-theme') === 'dark';
                if (escuro) raiz.removeAttribute('data-theme'); else raiz.setAttribute('data-theme', 'dark');
                try { localStorage.setItem('wallet-tema', escuro ? 'light' : 'dark'); } catch (e) {}
                rotula();
            });
            rotula();
        })();
    </script>
</body>
</html>
