import { expect, test } from '@playwright/test';
import { diaRelativo, SESSAO } from './apoio.js';

/**
 * O filtro por período, pelos campos de data do navegador.
 *
 * Ana é a pessoa com histórico: o seeder dá a ela dezenove lançamentos
 * espalhados pelas últimas cinco semanas, o suficiente para duas páginas. As
 * datas do cenário andam junto com o dia de hoje, então os testes contam os
 * dias a partir de hoje em vez de fixar uma data no calendário.
 */

test.use({ storageState: SESSAO.ana });

test.beforeEach(async ({ page }) => {
    await page.goto('/extrato');
});

test('filtra pelos campos de data e leva o período para a URL', async ({ page }) => {
    const inicio = diaRelativo(-40);
    const fim = diaRelativo(0);

    await page.getByLabel('Data inicial').fill(inicio);
    await page.getByLabel('Data final').fill(fim);
    await page.getByRole('button', { name: 'Filtrar' }).click();

    await expect(page).toHaveURL(new RegExp(`inicio=${inicio}&fim=${fim}`));
    // O período volta preenchido, para a pessoa ver o que está vendo.
    await expect(page.getByLabel('Data inicial')).toHaveValue(inicio);
    await expect(page.getByLabel('Data final')).toHaveValue(fim);
    await expect(page.getByRole('listitem')).not.toHaveCount(0);
});

test('a próxima página continua no mesmo período', async ({ page }) => {
    const inicio = diaRelativo(-40);
    const fim = diaRelativo(0);

    await page.getByLabel('Data inicial').fill(inicio);
    await page.getByLabel('Data final').fill(fim);
    await page.getByRole('button', { name: 'Filtrar' }).click();

    await expect(page.getByText('Página 1 de 2')).toBeVisible();

    await page.getByRole('link', { name: 'Mais antigas' }).click();

    await expect(page).toHaveURL(new RegExp(`inicio=${inicio}&fim=${fim}&page=2`));
    await expect(page.getByText('Página 2 de 2')).toBeVisible();
    await expect(page.getByLabel('Data inicial')).toHaveValue(inicio);
});

test('um período no futuro mostra que o vazio é do período', async ({ page }) => {
    await page.getByLabel('Data inicial').fill(diaRelativo(1));
    await page.getByLabel('Data final').fill(diaRelativo(30));
    await page.getByRole('button', { name: 'Filtrar' }).click();

    await expect(page.getByText('Nenhuma movimentação nesse período.')).toBeVisible();
    await expect(page.getByText('Nenhuma movimentação ainda.')).toHaveCount(0);
});

test('a data final anterior à inicial é recusada com a razão na tela', async ({ page }) => {
    await page.getByLabel('Data inicial').fill(diaRelativo(-1));
    await page.getByLabel('Data final').fill(diaRelativo(-10));
    await page.getByRole('button', { name: 'Filtrar' }).click();

    await expect(page.getByRole('alert')).toContainText('A data final não pode ser anterior à data inicial.');
    // A recusa devolve o extrato sem filtro, com as datas de volta nos campos.
    await expect(page.getByLabel('Data inicial')).toHaveValue(diaRelativo(-1));
    await expect(page.getByLabel('Data final')).toHaveValue(diaRelativo(-10));
});

test('só a data inicial já filtra', async ({ page }) => {
    const inicio = diaRelativo(-7);

    await page.getByLabel('Data inicial').fill(inicio);
    await page.getByRole('button', { name: 'Filtrar' }).click();

    await expect(page).toHaveURL(new RegExp(`inicio=${inicio}`));
    await expect(page).not.toHaveURL(/fim=\d/);
    await expect(page.getByLabel('Data final')).toHaveValue('');
});

test('Limpar devolve o extrato inteiro', async ({ page }) => {
    await page.getByLabel('Data inicial').fill(diaRelativo(-40));
    await page.getByLabel('Data final').fill(diaRelativo(0));
    await page.getByRole('button', { name: 'Filtrar' }).click();

    await page.getByRole('link', { name: 'Limpar' }).click();

    await expect(page).toHaveURL(/\/extrato$/);
    await expect(page.getByLabel('Data inicial')).toHaveValue('');
    await expect(page.getByLabel('Data final')).toHaveValue('');
    await expect(page.getByText('Página 1 de 2')).toBeVisible();
});
