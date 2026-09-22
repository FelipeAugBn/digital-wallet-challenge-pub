<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Wallet')</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 16px;
            background: #f4f5f7;
            color: #1b1e20;
            font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .card {
            max-width: 26rem;
            margin: 0 auto;
            padding: 24px;
            background: #fff;
            border: 1px solid #e2e5e8;
            border-radius: 8px;
        }
        .card.largo { max-width: 44rem; }
        h1 { margin: 0 0 4px; font-size: 1.35rem; }
        .sub { margin: 0 0 20px; color: #5c6367; font-size: .9rem; }
        label { display: block; margin-bottom: 4px; font-size: .9rem; font-weight: 600; }
        input {
            width: 100%;
            padding: 9px 10px;
            margin-bottom: 14px;
            border: 1px solid #c8cdd2;
            border-radius: 5px;
            font: inherit;
        }
        input:focus-visible { outline: 2px solid #0f6b5c; outline-offset: 1px; }
        button {
            width: 100%;
            padding: 10px;
            border: 0;
            border-radius: 5px;
            background: #0f6b5c;
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        .erros {
            margin: 0 0 16px;
            padding: 12px 12px 12px 30px;
            background: #fdf0ee;
            border: 1px solid #f0c7c0;
            border-radius: 5px;
            color: #8c2f20;
            font-size: .9rem;
        }
        .erros li + li { margin-top: 4px; }
        .ok {
            margin: 0 0 16px;
            padding: 12px;
            background: #eef6f3;
            border: 1px solid #bcdcd2;
            border-radius: 5px;
            color: #0f6b5c;
            font-size: .9rem;
        }
        .alt { margin: 18px 0 0; text-align: center; font-size: .9rem; }
        a { color: #0f6b5c; }
        .sair { margin-top: 20px; }
        .sair button { width: auto; padding: 8px 16px; background: #5c6367; }

        /* Navegacao compartilhada das telas autenticadas. */
        .barra {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 18px;
            align-items: center;
            max-width: 44rem;
            margin: 0 auto 16px;
            font-size: .9rem;
        }
        .barra .espaco { margin-left: auto; }
        .barra form { margin: 0; }
        .barra button {
            width: auto;
            padding: 4px 12px;
            background: transparent;
            border: 1px solid #c8cdd2;
            color: #5c6367;
            font-size: .85rem;
            font-weight: 500;
        }
        .barra a[aria-current] { font-weight: 700; text-decoration: none; }

        /* Saldo em destaque no painel. */
        .saldo { margin: 0 0 4px; font-size: 2rem; font-weight: 700; letter-spacing: -.02em; }
        .saldo.negativo { color: #8c2f20; }
        .rotulo { margin: 0 0 20px; color: #5c6367; font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; }

        .acoes { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 24px; }
        .acoes a {
            flex: 1 1 8rem;
            padding: 10px;
            border: 1px solid #0f6b5c;
            border-radius: 5px;
            text-align: center;
            text-decoration: none;
            font-size: .9rem;
            font-weight: 600;
        }
        .acoes a.primaria { background: #0f6b5c; color: #fff; }

        h2 { margin: 0 0 12px; font-size: 1rem; }

        /* Extrato: cada lancamento e uma linha que empilha no celular. */
        .extrato { margin: 0; padding: 0; list-style: none; }
        .extrato li {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 16px;
            align-items: baseline;
            padding: 12px 0;
            border-top: 1px solid #eceff1;
        }
        .extrato li:first-child { border-top: 0; }
        .extrato .descricao { flex: 1 1 14rem; min-width: 0; }
        .extrato .meta { display: block; margin-top: 2px; color: #5c6367; font-size: .8rem; }
        .extrato .valor {
            margin-left: auto;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            white-space: nowrap;
        }
        .extrato .valor.entrada { color: #0f6b5c; }
        .extrato .situacao {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 99px;
            background: #eceff1;
            color: #5c6367;
            font-size: .72rem;
            font-weight: 600;
        }
        .extrato .situacao.estornada { background: #fdf0ee; color: #8c2f20; }
        .extrato .estorno { margin: 0; }
        .extrato .estorno button {
            width: auto;
            padding: 4px 12px;
            background: transparent;
            border: 1px solid #c8cdd2;
            color: #5c6367;
            font-size: .8rem;
            font-weight: 600;
        }
        .vazio { margin: 0; padding: 14px 0; color: #5c6367; font-size: .9rem; }

        .paginas {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid #eceff1;
            font-size: .9rem;
        }
        .paginas .conta { margin: 0 auto; color: #5c6367; }

        @media (max-width: 30rem) {
            body { padding: 16px 12px; }
            .card { padding: 18px 16px; }
            .saldo { font-size: 1.6rem; }
            .acoes a { flex-basis: 100%; }
            .extrato .valor { margin-left: 0; flex-basis: 100%; }
        }
    </style>
</head>
<body>
    @auth
        <nav class="barra" aria-label="Navegação principal">
            <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Carteira</a>
            <a href="{{ route('deposits.create') }}" @if (request()->routeIs('deposits.create')) aria-current="page" @endif>Depositar</a>
            <a href="{{ route('transfers.create') }}" @if (request()->routeIs('transfers.create')) aria-current="page" @endif>Transferir</a>
            <a href="{{ route('statement') }}" @if (request()->routeIs('statement')) aria-current="page" @endif>Extrato</a>
            <form class="espaco" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Sair</button>
            </form>
        </nav>
    @endauth

    <main class="card @yield('largura')">
        @yield('conteudo')
    </main>
</body>
</html>
