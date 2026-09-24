import { expect, test } from '@playwright/test';

/**
 * O ícone da aba, entregue pelo servidor de verdade.
 *
 * A suíte Pest confere que o HTML aponta para os arquivos; só o servidor
 * prova que os arquivos chegam, com o tipo certo, sem passo de build.
 */
test('a aba recebe o ícone em SVG, com reserva para quem não o lê', async ({ page, request }) => {
    await page.goto('/login');

    const svg = page.locator('link[rel="icon"][type="image/svg+xml"]');
    await expect(svg).toHaveAttribute('href', /\/favicon\.svg$/);

    const resposta = await request.get(await svg.getAttribute('href'));
    expect(resposta.status()).toBe(200);
    expect(resposta.headers()['content-type']).toContain('image/svg+xml');
    expect(await resposta.text()).toContain('<svg');

    const reserva = await request.get('/favicon.ico');
    expect(reserva.status()).toBe(200);

    const toque = await request.get('/apple-touch-icon.png');
    expect(toque.status()).toBe(200);
    expect(toque.headers()['content-type']).toContain('image/png');
});
