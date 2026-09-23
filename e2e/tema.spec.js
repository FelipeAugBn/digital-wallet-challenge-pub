import { expect, test } from '@playwright/test';
import { SESSAO } from './apoio.js';

/**
 * O tema é a única coisa da aplicação que depende de JavaScript, e por isso é
 * a única que a suíte Pest não consegue provar: lá dá para conferir que o
 * botão está no HTML, não que clicar nele funciona.
 */

const raiz = (page) => page.locator('html');

test('o claro é o padrão, mesmo para quem usa o sistema no escuro', async ({ page }) => {
    // O navegador anuncia preferência por escuro; a aplicação decidiu que o
    // padrão é claro até a pessoa escolher, e é isso que precisa valer.
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/login');

    await expect(raiz(page)).not.toHaveAttribute('data-theme', 'dark');
    await expect(page.getByRole('button', { name: 'Ativar tema escuro' })).toBeVisible();
});

test('o botão troca o tema e diz para onde leva', async ({ page }) => {
    await page.goto('/login');

    const botao = page.getByRole('button', { name: 'Ativar tema escuro' });
    await expect(botao).toHaveAttribute('aria-pressed', 'false');
    await botao.click();

    await expect(raiz(page)).toHaveAttribute('data-theme', 'dark');

    const voltar = page.getByRole('button', { name: 'Ativar tema claro' });
    await expect(voltar).toHaveAttribute('aria-pressed', 'true');

    await voltar.click();
    await expect(raiz(page)).not.toHaveAttribute('data-theme', 'dark');
});

test.describe('já dentro da conta', () => {
    test.use({ storageState: SESSAO.ana });

test('a escolha sobrevive ao recarregamento e acompanha as outras telas', async ({ page }) => {
    await page.goto('/dashboard');

    await page.getByRole('button', { name: 'Ativar tema escuro' }).click();
    await expect(raiz(page)).toHaveAttribute('data-theme', 'dark');

    await page.reload();
    await expect(raiz(page)).toHaveAttribute('data-theme', 'dark');

    await page.goto('/extrato');
    await expect(raiz(page)).toHaveAttribute('data-theme', 'dark');
});

});

test('o escuro já está aplicado no primeiro quadro, sem piscar claro', async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('button', { name: 'Ativar tema escuro' }).click();

    // Lê o tema no instante em que o HTML começa a chegar: se dependesse de um
    // script no fim da página, aqui ainda estaria claro.
    await page.goto('/extrato', { waitUntil: 'commit' });
    const temaNoComeco = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));

    expect(temaNoComeco).toBe('dark');
});

test('o tema muda o que se vê, não só um atributo', async ({ page }) => {
    await page.goto('/login');
    const fundo = () => page.locator('body').evaluate((corpo) => getComputedStyle(corpo).backgroundColor);

    const claro = await fundo();
    await page.getByRole('button', { name: 'Ativar tema escuro' }).click();
    const escuro = await fundo();

    expect(escuro).not.toBe(claro);

    // Escuro é escuro de verdade: a soma dos canais cai bem abaixo da do claro.
    const soma = (cor) => cor.match(/\d+/g).slice(0, 3).reduce((total, canal) => total + Number(canal), 0);
    expect(soma(escuro)).toBeLessThan(soma(claro) / 2);
});
