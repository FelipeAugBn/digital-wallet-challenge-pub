<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public const HEADER = 'X-Request-ID';

    public const ATRIBUTO = 'request_id';

    /**
     * Da a cada requisicao um identificador gerado aqui.
     *
     * O valor que o cliente mandou no header e sobrescrito antes de qualquer
     * rota, log ou tela poder le-lo: se ele fosse aproveitado, bastaria repetir
     * o mesmo valor para misturar requisicoes diferentes na hora de investigar
     * um problema pelo log.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid7();

        $request->headers->set(self::HEADER, $requestId);
        $request->attributes->set(self::ATRIBUTO, $requestId);

        Log::withContext([self::ATRIBUTO => $requestId]);

        $response = $next($request);

        // Tambem nas respostas de erro: este middleware e o primeiro da fila, e
        // a resposta renderizada pelo tratador de excecoes volta por aqui.
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
