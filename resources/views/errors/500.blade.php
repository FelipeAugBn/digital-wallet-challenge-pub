@php($requestId = $requestId ?? request()->attributes->get(\App\Http\Middleware\AssignRequestId::ATRIBUTO))
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Algo deu errado · Wallet</title>
    {{-- Esta pagina nao usa o layout comum de proposito: ela precisa aparecer
         mesmo quando a falha esta justamente no que o layout consulta. --}}
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
        .codigo {
            padding: 10px 12px;
            background: #f4f5f7;
            border: 1px solid #e2e5e8;
            border-radius: 5px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .82rem;
            word-break: break-all;
        }
        .alt { margin: 18px 0 0; text-align: center; font-size: .9rem; }
        a { color: #0f6b5c; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Algo deu errado</h1>
        <p class="sub">A operação não foi concluída.</p>
        <p>Operações financeiras acontecem por inteiro ou não acontecem: nada ficou pela metade. Tente novamente em alguns instantes.</p>
        <p>Se o problema continuar, informe este código a quem cuida do sistema:</p>
        <p class="codigo">{{ $requestId }}</p>
        <p class="alt"><a href="{{ url('/') }}">Voltar ao início</a></p>
    </main>
</body>
</html>
