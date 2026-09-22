<?php

use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Primeiro da fila: tudo que vier depois, inclusive a tela de erro,
        // ja encontra o identificador desta requisicao pronto.
        $middleware->prepend(AssignRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $falha, Request $request) {
            // Erro esperado segue o caminho normal do Laravel: validacao volta
            // ao formulario com as mensagens, 403 continua 403, 404 continua
            // 404 e o limite de requisicoes continua devolvendo o proprio
            // redirect. So o que ninguem previu chega ao fim desta lista.
            $esperado = $falha instanceof HttpExceptionInterface
                || $falha instanceof HttpResponseException
                || $falha instanceof ValidationException
                || $falha instanceof AuthenticationException;

            if ($esperado || app()->isLocal()) {
                return null;
            }

            // Fora do ambiente local a pessoa recebe apenas o identificador da
            // requisicao. O detalhe da falha fica no log, que carrega o mesmo
            // identificador e junta os dois lados sem expor nada na tela.
            return response()->view('errors.500', status: 500);
        });
    })->create();
