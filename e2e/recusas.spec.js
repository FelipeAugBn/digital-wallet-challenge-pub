import { expect, test } from '@playwright/test';
import { cadastra, DEMONSTRACAO, emailNovo, menu, saldo, SESSAO } from './apoio.js';

/**
 * As recusas, vistas de fora.
 *
 * O servidor já tem teste para cada uma. Aqui o que se confere é o que a
 * pessoa encontra na tela depois de enviar o formulário: a mensagem em
 * português, o campo marcado e o dinheiro parado onde estava.
 */

test('recusa a transferência que o saldo não cobre e não mexe na carteira', async ({ page }) => {
    await cadastra(page, 'Tiago Alves', emailNovo('tiago'));

    await menu(page).getByRole('link', { name: 'Transferir' }).click();
    await page.getByLabel('E-mail de quem vai receber').fill(DEMONSTRACAO.bruno.email);
    await page.getByLabel('Valor').fill('10,00');
    await page.getByRole('button', { name: 'Transferir' }).click();

    await expect(page.getByRole('alert')).toContainText('Saldo insuficiente');
    // O campo recusado fica marcado para quem enxerga e para quem ouve a tela.
    await expect(page.getByLabel('Valor')).toHaveAttribute('aria-invalid', 'true');

    await menu(page).getByRole('link', { name: 'Carteira' }).click();
    await expect(saldo(page)).toHaveText('R$ 0,00');
    await expect(page.getByText('Nenhuma movimentação ainda.')).toBeVisible();
});

test('recusa o destinatário que não existe, sem dizer quem existe', async ({ page }) => {
    await cadastra(page, 'Rita Souza', emailNovo('rita'));

    await menu(page).getByRole('link', { name: 'Depositar' }).click();
    await page.getByLabel('Valor').fill('100,00');
    await page.getByRole('button', { name: 'Depositar' }).click();

    await menu(page).getByRole('link', { name: 'Transferir' }).click();
    await page.getByLabel('E-mail de quem vai receber').fill('ninguem@exemplo.test');
    await page.getByLabel('Valor').fill('10,00');
    await page.getByRole('button', { name: 'Transferir' }).click();

    await expect(page.getByRole('alert')).toBeVisible();
    await expect(page.getByLabel('E-mail de quem vai receber')).toHaveAttribute('aria-invalid', 'true');

    await menu(page).getByRole('link', { name: 'Carteira' }).click();
    await expect(saldo(page)).toHaveText('R$ 100,00');
});

test('recusa a transferência para a própria carteira', async ({ page }) => {
    const email = emailNovo('paulo');
    await cadastra(page, 'Paulo Lima', email);

    await menu(page).getByRole('link', { name: 'Depositar' }).click();
    await page.getByLabel('Valor').fill('50,00');
    await page.getByRole('button', { name: 'Depositar' }).click();

    await menu(page).getByRole('link', { name: 'Transferir' }).click();
    await page.getByLabel('E-mail de quem vai receber').fill(email);
    await page.getByLabel('Valor').fill('10,00');
    await page.getByRole('button', { name: 'Transferir' }).click();

    await expect(page.getByRole('alert')).toBeVisible();

    await menu(page).getByRole('link', { name: 'Carteira' }).click();
    await expect(saldo(page)).toHaveText('R$ 50,00');
});

test('recusa o valor escrito fora do formato brasileiro', async ({ page }) => {
    await cadastra(page, 'Lena Dias', emailNovo('lena'));

    await menu(page).getByRole('link', { name: 'Depositar' }).click();
    await page.getByLabel('Valor').fill('1000.50');
    await page.getByRole('button', { name: 'Depositar' }).click();

    await expect(page.getByRole('alert')).toBeVisible();
    await expect(page.getByLabel('Valor')).toHaveAttribute('aria-invalid', 'true');
});

test('recusa credenciais erradas sem dizer se a conta existe', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(DEMONSTRACAO.ana.email);
    await page.getByLabel('Senha').fill('senha-que-nao-e-a-dela');
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('alert')).toContainText('E-mail ou senha incorretos.');
});

test.describe('com a sessão da Carla', () => {
    test.use({ storageState: SESSAO.carla });

test('o formulário enviado sem o token da sessão é barrado', async ({ page }) => {
    await page.goto('/deposits');

    // Um token adulterado é o que um envio forjado de outro site traria. A
    // página de erro aparece e nenhum depósito acontece.
    const ficha = page.getByRole('region', { name: 'Formulário de depósito' });
    await ficha.locator('input[name="_token"]').evaluate((campo) => {
        campo.value = 'token-de-outro-lugar';
    });
    await page.getByLabel('Valor').fill('10,00');
    await page.getByRole('button', { name: 'Depositar' }).click();

    await expect(page.locator('body')).not.toContainText('Depósito de R$ 10,00 realizado.');

    await page.goto('/dashboard');
    await expect(saldo(page)).toHaveText('R$ 350,00');
});

});
