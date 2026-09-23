import { execFileSync } from 'node:child_process';
import { expect, test as preparo } from '@playwright/test';
import { DEMONSTRACAO, entra, SESSAO } from './apoio.js';
import { doAmbiente } from './preparar-banco.js';

/**
 * O que roda antes de qualquer teste tocar em dinheiro.
 *
 * Primeiro a conferência de que o servidor sob teste escreve no banco do E2E,
 * e não em outro. Depois as sessões, guardadas uma vez só.
 */

/**
 * Prova, de fora, em qual banco o servidor escreve.
 *
 * Não adianta perguntar ao container qual variável ele recebeu: `artisan
 * serve` repassa ao servidor apenas uma lista fixa de variáveis, e já deixou
 * um servidor apontado para o banco errado uma vez. Então a conferência é
 * feita pelo efeito: abrir uma página cria uma sessão, e a sessão tem de
 * aparecer no banco do E2E. Se aparecer em outro lugar, aqui fica vazio e a
 * execução para antes de estragar alguma coisa.
 */
preparo('o servidor sob teste escreve no banco do E2E', async ({ page }) => {
    const banco = doAmbiente('DB_DATABASE');
    const cookie = doAmbiente('SESSION_COOKIE');

    const contaSessoes = () => Number(
        execFileSync('docker', [
            'compose', 'exec', '-T', 'pgsql', 'sh', '-c',
            `psql -U "$POSTGRES_USER" -d ${banco} -tAc 'select count(*) from sessions'`,
        ], { encoding: 'utf8' }).trim(),
    );

    const antes = contaSessoes();
    await page.goto('/login');
    await expect(page.getByRole('heading', { name: 'Entrar' })).toBeVisible();

    // O cookie leva o nome que só `.env.e2e` define: se viesse o nome do
    // ambiente de desenvolvimento, o servidor seria o outro.
    const nomes = (await page.context().cookies()).map((biscoito) => biscoito.name);
    expect(
        nomes,
        `O servidor em teste não é o do E2E: o cookie de sessão devia se chamar "${cookie}" e vieram ${JSON.stringify(nomes)}. `
        + 'Derrube qualquer servidor antigo na porta do E2E antes de rodar de novo.',
    ).toContain(cookie);

    expect(
        contaSessoes(),
        `Abrir a página não criou sessão em "${banco}": o servidor está escrevendo em outro banco. `
        + 'A execução para aqui para não mexer em dados que não são do teste.',
    ).toBeGreaterThan(antes);
});

preparo('guarda a sessão da Ana', async ({ page }) => {
    await entra(page, DEMONSTRACAO.ana.email);
    await page.context().storageState({ path: SESSAO.ana });
});

preparo('guarda a sessão da Carla', async ({ page }) => {
    await entra(page, DEMONSTRACAO.carla.email);
    await page.context().storageState({ path: SESSAO.carla });
});
