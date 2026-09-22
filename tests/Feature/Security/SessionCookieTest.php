<?php

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

/** O cookie de sessao que a resposta mandou para o navegador. */
function cookieDeSessao(TestResponse $resposta): Cookie
{
    $nome = config('session.cookie');

    foreach ($resposta->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $nome) {
            return $cookie;
        }
    }

    throw new RuntimeException('A resposta não trouxe o cookie de sessão.');
}

test('entrega o cookie de sessão como HttpOnly e SameSite Lax', function () {
    $cookie = cookieDeSessao($this->get(route('login')));

    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe(Cookie::SAMESITE_LAX);
});

test('marca o cookie como Secure quando a resposta sai por HTTPS', function () {
    $cookie = cookieDeSessao($this->get('https://localhost/login'));

    expect($cookie->isSecure())->toBeTrue();
});

test('não marca o cookie como Secure quando a resposta sai por HTTP', function () {
    // Marcar Secure em HTTP faria o navegador descartar o cookie e a sessao
    // sumir no ambiente local, que roda sem certificado.
    $cookie = cookieDeSessao($this->get('http://localhost/login'));

    expect($cookie->isSecure())->toBeFalse();
});

test('mantém a configuração de sessão sem brecha', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        // Em branco de proposito: assim o `Secure` acompanha o esquema da
        // resposta em vez de ficar preso a um dos dois casos.
        ->and(config('session.secure'))->toBeNull();
});

test('documenta as três opções do cookie no .env.example', function () {
    $exemplo = file_get_contents(base_path('.env.example'));

    expect($exemplo)->toContain('SESSION_HTTP_ONLY=true')
        ->and($exemplo)->toContain('SESSION_SAME_SITE=lax')
        ->and($exemplo)->toContain('SESSION_SECURE_COOKIE');
});
