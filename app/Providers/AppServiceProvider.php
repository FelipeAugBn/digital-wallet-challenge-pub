<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configuraLimites();
    }

    /**
     * Os dois limites da secao 8 da SPEC, definidos num lugar so.
     *
     * Ficam aqui, e nao na rota, porque a chave de cada um e uma decisao de
     * seguranca: quem le a rota ve apenas que o limite existe, e quem precisa
     * conferir de que jeito ele conta vem direto a este arquivo.
     */
    private function configuraLimites(): void
    {
        RateLimiter::for('login', function (Request $request) {
            // Contar por IP e e-mail juntos protege a conta sem trancar todo
            // mundo que divide o mesmo IP. Quem varre e-mails a partir de um IP
            // so ganha cota nova a cada e-mail: e o que a SPEC pede, e o freio
            // desse caso e a senha forte do cadastro, nao este limite.
            $chave = 'login|'.$request->ip().'|'.$this->emailNormalizado($request);

            return Limit::perMinute(5)->by($chave)->response(
                fn (Request $request, array $headers) => back(fallback: route('login'))
                    ->withErrors(['limite' => 'Muitas tentativas de login. Tente novamente em '.$headers['Retry-After'].' segundos.'])
                    ->withHeaders($headers)
            );
        });

        RateLimiter::for('financial', function (Request $request) {
            // A cota e da pessoa autenticada, nao do IP: duas pessoas na mesma
            // rede nao disputam o mesmo teto, e trocar de IP no meio nao
            // devolve cota a ninguem.
            return Limit::perMinute(20)->by('financial|'.$request->user()->getAuthIdentifier())->response(
                fn (Request $request, array $headers) => back(fallback: route('dashboard'))
                    ->withErrors(['limite' => 'Muitas operações em pouco tempo. Tente novamente em '.$headers['Retry-After'].' segundos.'])
                    ->withHeaders($headers)
            );
        });
    }

    /**
     * O e-mail em minusculas e sem espacos nas pontas.
     *
     * O limite corre antes da validacao, entao o campo ainda pode vir com
     * qualquer coisa dentro, inclusive uma lista: o que nao for texto vale como
     * tentativa sem e-mail, e nao como chave nova a cada formato inventado.
     */
    private function emailNormalizado(Request $request): string
    {
        $email = $request->input('email');

        return is_string($email) ? Str::lower(trim($email)) : '';
    }
}
