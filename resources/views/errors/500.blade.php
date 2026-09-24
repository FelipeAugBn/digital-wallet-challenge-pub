@php($requestId = $requestId ?? request()->attributes->get(\App\Http\Middleware\AssignRequestId::ATRIBUTO))
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Algo deu errado · Wallet</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Esta pagina nao usa o layout comum de proposito: ela precisa aparecer
         mesmo quando a falha esta justamente no que o layout consulta. Por isso
         as cores sao repetidas aqui, e nao herdadas. --}}
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 16px;
            background: #f4f7f5;
            color: #10211d;
            font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .folha {
            width: min(26rem, 100% - 2rem);
            margin: 24px auto;
            padding: 28px;
            background: #fff;
            border: 1px solid #dde5e1;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(16, 33, 29, .04);
        }
        h1 { margin: 0 0 4px; font-size: 1.3rem; font-weight: 600; letter-spacing: -.01em; }
        .sub { margin: 0 0 20px; color: #47605a; font-size: .9375rem; }
        p { margin: 0 0 14px; }
        .codigo {
            margin: 0;
            padding: 11px 12px;
            background: #f1f5f3;
            border: 1px solid #dde5e1;
            border-radius: 4px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .8125rem;
            word-break: break-all;
        }
        .alt {
            margin: 24px 0 0;
            padding-top: 18px;
            border-top: 1px solid #dde5e1;
            color: #47605a;
            font-size: .9375rem;
            text-align: center;
        }
        a { color: #0f6b5c; text-underline-offset: 2px; }
        :focus-visible { outline: 2px solid #0f6b5c; outline-offset: 2px; border-radius: 3px; }
    </style>
</head>
<body>
    <main class="folha">
        <h1>Algo deu errado</h1>
        <p class="sub">A operação não foi concluída.</p>
        <p>Operações financeiras acontecem por inteiro ou não acontecem: nada ficou pela metade. Tente novamente em alguns instantes.</p>
        <p>Se o problema continuar, informe este código a quem cuida do sistema:</p>
        <p class="codigo">{{ $requestId }}</p>
        <p class="alt"><a href="{{ url('/') }}">Voltar ao início</a></p>
    </main>
</body>
</html>
