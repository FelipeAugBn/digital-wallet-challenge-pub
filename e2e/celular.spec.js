import { expect, test } from '@playwright/test';
import { DEMONSTRACAO, menu, SESSAO } from './apoio.js';

/**
 * A tela pequena, num navegador de celular de verdade.
 *
 * Nenhum teste de servidor enxerga isto: só o navegador sabe se a página
 * escorre de lado, se o formulário cabe e se dá para chegar ao extrato com o
 * polegar.
 */

/** Quantos pixels a página passa da largura da tela. Zero ou menos é o certo. */
async function excessoHorizontal(page) {
    return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

const COM_SESSAO = { storageState: SESSAO.ana };

const TELAS = [
    ['a página inicial', '/'],
    ['o formulário de entrada', '/login'],
    ['o cadastro', '/register'],
];

for (const [nome, endereco] of TELAS) {
    test(`${nome} cabe na largura do celular`, async ({ page }) => {
        await page.goto(endereco);

        expect(await excessoHorizontal(page)).toBeLessThanOrEqual(0);
    });
}

test.describe('já dentro da conta', () => {
    test.use(COM_SESSAO);

test('o painel, com gráficos e tudo, cabe na largura do celular', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page.getByRole('heading', { name: `Olá, ${DEMONSTRACAO.ana.nome}` })).toBeVisible();
    expect(await excessoHorizontal(page)).toBeLessThanOrEqual(0);
});

test('o extrato filtrado cabe na largura do celular', async ({ page }) => {
    await page.goto('/extrato');

    expect(await excessoHorizontal(page)).toBeLessThanOrEqual(0);

    // O filtro também tem de caber, com os dois campos e os dois botões.
    await expect(page.getByLabel('Data inicial')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Filtrar' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Limpar' })).toBeVisible();
});

test('dá para chegar ao extrato e voltar pelo menu do topo', async ({ page }) => {
    await page.goto('/dashboard');

    await menu(page).getByRole('link', { name: 'Extrato' }).click();
    await expect(page.getByRole('heading', { name: 'Extrato' })).toBeVisible();

    await menu(page).getByRole('link', { name: 'Carteira' }).click();
    await expect(page.getByRole('heading', { name: `Olá, ${DEMONSTRACAO.ana.nome}` })).toBeVisible();
});

test('os alvos de toque do menu têm altura de dedo', async ({ page }) => {
    await page.goto('/dashboard');

    const links = await menu(page).getByRole('link').all();
    expect(links.length).toBe(4);

    for (const link of links) {
        const caixa = await link.boundingBox();
        expect(caixa.height).toBeGreaterThanOrEqual(32);
    }
});
});
