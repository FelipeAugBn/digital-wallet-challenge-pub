<?php

namespace App\Logging;

use Illuminate\Support\Str;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactSensitiveValues implements ProcessorInterface
{
    /** Pedacos de nome que denunciam um campo que nao pode virar log. */
    private const SENSIVEIS = [
        'password',
        'senha',
        'token',
        'csrf',
        'cookie',
        'authorization',
        'secret',
        'segredo',
        'api_key',
        'apikey',
    ];

    private const MARCA = '[redigido]';

    /**
     * Troca por uma marca o valor de todo campo com cara de segredo.
     *
     * A aplicacao ja nao manda senha nem cookie para o log, mas isso depende de
     * cada chamada continuar cuidadosa. Aqui a garantia passa a ser do canal:
     * mesmo que alguem registre o formulario inteiro um dia, a senha nao sai.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redigir($record->context),
            extra: $this->redigir($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $dados
     * @return array<array-key, mixed>
     */
    private function redigir(array $dados): array
    {
        foreach ($dados as $chave => $valor) {
            if (is_array($valor)) {
                $dados[$chave] = $this->redigir($valor);

                continue;
            }

            if ($this->sensivel((string) $chave)) {
                $dados[$chave] = self::MARCA;
            }
        }

        return $dados;
    }

    private function sensivel(string $chave): bool
    {
        return Str::contains(Str::lower($chave), self::SENSIVEIS);
    }
}
