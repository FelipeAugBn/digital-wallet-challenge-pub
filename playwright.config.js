import { defineConfig, devices } from '@playwright/test';

/**
 * Os testes de ponta a ponta: um navegador de verdade contra a aplicação de
 * verdade.
 *
 * A suíte Pest cobre o servidor inteiro e é a que decide se uma regra
 * financeira está certa. O que só um navegador prova é o que acontece depois
 * do HTML: o token CSRF que volta no formulário enviado por uma pessoa, o
 * campo de data nativo, o botão de tema, a página que não escorre de lado no
 * celular. É disso que estes arquivos tratam.
 */

/** A aplicação sobe numa porta própria, longe da que você usa para trabalhar. */
const PORTA = process.env.E2E_PORT ?? '8081';
const ENDERECO = process.env.E2E_URL ?? `http://localhost:${PORTA}`;

/** O container roda com o seu usuário, como o Sail faz. */
const USUARIO = typeof process.getuid === 'function' ? process.getuid() : 1000;

export default defineConfig({
    testDir: './e2e',

    // Um banco só, com dinheiro saindo de uma carteira e entrando em outra: os
    // testes correm em fila para que o saldo que um lê seja o que ele deixou.
    fullyParallel: false,
    workers: 1,

    forbidOnly: !! process.env.CI,
    retries: 0,
    timeout: 30_000,
    expect: { timeout: 7_000 },
    reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list'], ['html', { open: 'never' }]],

    globalSetup: './e2e/preparar-banco.js',
    globalTeardown: './e2e/encerrar-servidor.js',

    use: {
        baseURL: ENDERECO,
        // A aplicação fala português e guarda tudo em UTC; o navegador do teste
        // também, senão o campo de data e os rótulos de dia discordariam.
        locale: 'pt-BR',
        timezoneId: 'UTC',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },

    projects: [
        // Entra uma vez com cada pessoa e guarda a sessão: o limite de cinco
        // tentativas de login por minuto é uma proteção que os testes
        // respeitam, em vez de contornar.
        {
            name: 'preparo',
            testMatch: /autenticar\.setup\.js/,
        },
        {
            name: 'computador',
            use: { ...devices['Desktop Chrome'] },
            testIgnore: /celular\.spec\.js/,
            dependencies: ['preparo'],
        },
        {
            name: 'celular',
            use: { ...devices['Pixel 5'] },
            testMatch: /celular\.spec\.js/,
            dependencies: ['preparo'],
        },
    ],

    // Um container efêmero, com a porta publicada só para esta execução: o
    // `docker compose up` que você deixou rodando continua onde está, servindo
    // o banco de desenvolvimento na porta de sempre.
    //
    // O banco não vem por `-e DB_DATABASE`: `artisan serve` repassa ao servidor
    // apenas uma lista fixa de variáveis e descartaria essa em silêncio, o que
    // deixaria o E2E escrevendo no banco de desenvolvimento. Quem aponta o
    // banco é `APP_ENV=e2e`, que está na lista e faz o Laravel ler `.env.e2e`.
    webServer: {
        command: [
            'docker compose run --rm',
            '--name wallet-e2e',
            `--publish ${PORTA}:${PORTA}`,
            `-e WWWUSER=${USUARIO}`,
            '-e APP_ENV=e2e',
            'laravel.test',
            `php artisan serve --host=0.0.0.0 --port=${PORTA}`,
        ].join(' '),
        url: `${ENDERECO}/up`,
        // Nunca reaproveitar: um servidor que já estivesse nessa porta poderia
        // estar apontado para outro banco, e foi exatamente assim que o E2E
        // escreveu onde não devia uma vez.
        reuseExistingServer: false,
        timeout: 120_000,
        gracefulShutdown: { signal: 'SIGTERM', timeout: 10_000 },
        stdout: 'ignore',
        stderr: 'pipe',
    },
});
