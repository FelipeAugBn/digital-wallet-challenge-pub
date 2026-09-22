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
        .alt { margin: 18px 0 0; text-align: center; font-size: .9rem; }
        a { color: #0f6b5c; }
        .sair { margin-top: 20px; }
        .sair button { width: auto; padding: 8px 16px; background: #5c6367; }
    </style>
</head>
<body>
    <main class="card">
        @yield('conteudo')
    </main>
</body>
</html>
