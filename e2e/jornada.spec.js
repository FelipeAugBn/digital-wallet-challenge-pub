import { expect, test } from '@playwright/test';
import { cadastra, DEMONSTRACAO, emailNovo, linhaDoExtrato, menu, saldo } from './apoio.js';

/**
 * A jornada inteira, num navegador só: cadastrar, depositar, transferir,
 * conferir o extrato, estornar e sair.
 *
 * Cada passo daqui já tem teste no Pest. O que este arquivo prova é o encaixe:
 * que os formulários levam o token CSRF de verdade, que o redirect leva à tela
 * certa e que o dinheiro que aparece numa tela é o mesmo da seguinte.
 */
test('do cadastro ao estorno, tudo pela tela', async ({ page }) => {
    const email = emailNovo('joana');

    await test.step('cadastra e cai na carteira zerada', async () => {
        await cadastra(page, 'Joana Prado', email);

        await expect(page.getByRole('heading', { name: 'Olá, Joana Prado' })).toBeVisible();
        await expect(saldo(page)).toHaveText('R$ 0,00');
        await expect(page.getByText('Nenhuma movimentação ainda.')).toBeVisible();
    });

    await test.step('deposita e vê o saldo subir', async () => {
        await menu(page).getByRole('link', { name: 'Depositar' }).click();
        await page.getByLabel('Valor').fill('1.000,50');
        await page.getByRole('button', { name: 'Depositar' }).click();

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByText('Depósito de R$ 1.000,50 realizado.')).toBeVisible();
        await expect(saldo(page)).toHaveText('R$ 1.000,50');
    });

    await test.step('recarregar a página do redirect não deposita de novo', async () => {
        await page.reload();

        await expect(saldo(page)).toHaveText('R$ 1.000,50');
    });

    await test.step('transfere para quem já usa a carteira', async () => {
        await menu(page).getByRole('link', { name: 'Transferir' }).click();

        // O formulário mostra de quanto ela parte, antes de digitar.
        await expect(saldo(page)).toHaveText('R$ 1.000,50');

        await page.getByLabel('E-mail de quem vai receber').fill(DEMONSTRACAO.bruno.email);
        await page.getByLabel('Valor').fill('250,00');
        await page.getByRole('button', { name: 'Transferir' }).click();

        await expect(page.getByText(`Transferência de R$ 250,00 para ${DEMONSTRACAO.bruno.email} realizada.`)).toBeVisible();
        await expect(saldo(page)).toHaveText('R$ 750,50');
    });

    await test.step('o extrato conta as duas operações', async () => {
        await menu(page).getByRole('link', { name: 'Extrato' }).click();

        await expect(page.getByRole('heading', { name: 'Extrato' })).toBeVisible();
        await expect(page.getByText('R$ 750,50')).toBeVisible();

        const deposito = linhaDoExtrato(page, 'Depósito');
        const transferencia = linhaDoExtrato(page, `Transferência enviada para ${DEMONSTRACAO.bruno.nome}`);

        await expect(deposito.getByText('+R$ 1.000,50')).toBeVisible();
        await expect(transferencia.getByText('-R$ 250,00')).toBeVisible();
    });

    await test.step('estorna a transferência e o dinheiro volta', async () => {
        const transferencia = linhaDoExtrato(page, `Transferência enviada para ${DEMONSTRACAO.bruno.nome}`);
        await transferencia.getByRole('button', { name: 'Estornar' }).click();

        await expect(page.getByText('Estorno de R$ 250,00 realizado.')).toBeVisible();

        // A operação original continua no extrato, marcada, e o estorno entra
        // como uma linha nova: o histórico não perde nada.
        await expect(linhaDoExtrato(page, `Estorno de transferência enviada para ${DEMONSTRACAO.bruno.nome}`)).toBeVisible();
        await expect(page.getByText('Estornada')).toBeVisible();

        await menu(page).getByRole('link', { name: 'Carteira' }).click();
        await expect(saldo(page)).toHaveText('R$ 1.000,50');
    });

    await test.step('o que já foi estornado não oferece o botão de novo', async () => {
        await menu(page).getByRole('link', { name: 'Extrato' }).click();

        const transferencia = linhaDoExtrato(page, `Transferência enviada para ${DEMONSTRACAO.bruno.nome}`).first();
        await expect(transferencia.getByRole('button', { name: 'Estornar' })).toHaveCount(0);
    });

    await test.step('sai da conta e a carteira fica fechada', async () => {
        await page.getByRole('button', { name: 'Sair' }).click();
        await expect(page).toHaveURL(/\/login$/);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login$/);
    });
});
