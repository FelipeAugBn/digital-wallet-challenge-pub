import { expect } from '@playwright/test';

/**
 * As pessoas que o seeder cria, com a senha que ele anuncia na tela.
 *
 * Não é segredo: são as credenciais de demonstração, as mesmas impressas ao
 * fim de `migrate --seed` e citadas no README.
 */
export const DEMONSTRACAO = {
    senha: 'demonstracao',
    ana: { email: 'ana@wallet.test', nome: 'Ana Ribeiro' },
    bruno: { email: 'bruno@wallet.test', nome: 'Bruno Carvalho' },
    carla: { email: 'carla@wallet.test', nome: 'Carla Nogueira' },
};

/** Onde ficam as sessões que o preparo guarda, para os testes reaproveitarem. */
export const SESSAO = {
    ana: 'e2e/.sessoes/ana.json',
    carla: 'e2e/.sessoes/carla.json',
};

/** Um e-mail que só existe nesta execução, para não esbarrar em outro teste. */
export function emailNovo(prefixo) {
    return `${prefixo}-${Date.now()}-${Math.floor(Math.random() * 1_000)}@exemplo.test`;
}

/** Uma data em AAAA-MM-DD, contada a partir de hoje em UTC, que é o fuso da aplicação. */
export function diaRelativo(dias) {
    const data = new Date();
    data.setUTCDate(data.getUTCDate() + dias);

    return data.toISOString().slice(0, 10);
}

/** Entra na conta pelo formulário, como qualquer pessoa entraria. */
export async function entra(page, email, senha = DEMONSTRACAO.senha) {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill(senha);
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
}

/** Cadastra alguém novo; o cadastro já abre a carteira e a sessão. */
export async function cadastra(page, nome, email, senha = 'segredo-bem-guardado') {
    await page.goto('/register');
    await page.getByLabel('Nome').fill(nome);
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha', { exact: true }).fill(senha);
    await page.getByLabel('Confirme a senha').fill(senha);
    await page.getByRole('button', { name: 'Criar conta' }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
}

/** O menu do topo, que é por onde se anda pela aplicação. */
export function menu(page) {
    return page.getByRole('navigation', { name: 'Navegação principal' });
}

/** O saldo que o painel ou o formulário mostra em destaque. */
export function saldo(page) {
    return page.locator('.saldo');
}

/** A linha do extrato que fala de uma operação. */
export function linhaDoExtrato(page, texto) {
    return page.getByRole('listitem').filter({ hasText: texto });
}
