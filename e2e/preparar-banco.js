import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, readFileSync } from 'node:fs';

/**
 * Deixa o E2E pronto: o arquivo de ambiente, o banco e o cenário semeado.
 *
 * O servidor do E2E sobe com `APP_ENV=e2e`, e é isso que faz o Laravel ler
 * `.env.e2e` em vez de `.env`. O caminho é esse, e não uma variável solta na
 * linha de comando, porque `artisan serve` repassa ao servidor apenas uma
 * lista fixa de variáveis: `DB_DATABASE` não está nela e seria descartada em
 * silêncio, deixando o servidor no banco de desenvolvimento. `APP_ENV` está.
 *
 * Quem confere se isso tudo deu certo é `autenticar.setup.js`, antes de
 * qualquer teste tocar em dinheiro.
 */

const AMBIENTE = '.env.e2e';
const MODELO = '.env.e2e.example';

/** Os bancos que o E2E nunca pode tocar. */
const PROIBIDOS = {
    wallet: 'é o banco de desenvolvimento, com os dados que você criou à mão',
    wallet_testing: 'é o banco da suíte Pest, que roda em paralelo a isto',
};

function compose(argumentos, descricao) {
    try {
        return execFileSync('docker', ['compose', ...argumentos], {
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        });
    } catch (falha) {
        const saida = [falha.stdout, falha.stderr].filter(Boolean).join('\n').trim();

        throw new Error(`Não foi possível ${descricao}.\n\n${saida}`);
    }
}

/** Lê uma chave do arquivo de ambiente do E2E, que não guarda segredo nenhum. */
export function doAmbiente(chave) {
    const linha = readFileSync(AMBIENTE, 'utf8')
        .split('\n')
        .find((texto) => texto.startsWith(`${chave}=`));

    return linha?.slice(chave.length + 1).trim().replace(/^"|"$/g, '') ?? null;
}

export default function preparaBanco() {
    if (! existsSync(AMBIENTE)) {
        copyFileSync(MODELO, AMBIENTE);
        compose(['exec', '-T', 'laravel.test', 'php', 'artisan', 'key:generate', '--env=e2e', '--force'], `gerar a chave de ${AMBIENTE}`);
        console.log(`E2E: ${AMBIENTE} criado a partir de ${MODELO}.`);
    }

    const banco = doAmbiente('DB_DATABASE');

    if (banco in PROIBIDOS) {
        throw new Error(
            `O E2E não roda em "${banco}": ele ${PROIBIDOS[banco]}. `
            + `Corrija DB_DATABASE em ${AMBIENTE}.`,
        );
    }

    if (! /^[a-z0-9_]+$/.test(banco ?? '')) {
        throw new Error(`Nome de banco inesperado em ${AMBIENTE}: "${banco}".`);
    }

    // O usuário e a senha do Postgres ficam no ambiente do próprio container,
    // onde o Compose já os colocou: nada de credencial escrita aqui.
    compose([
        'exec', '-T', 'pgsql', 'sh', '-c',
        `psql -U "$POSTGRES_USER" -d postgres -tAc "select 1 from pg_database where datname = '${banco}'" | grep -q 1 `
        + `|| psql -U "$POSTGRES_USER" -d postgres -c 'create database ${banco}'`,
    ], `criar o banco ${banco}`);

    compose([
        'exec', '-T', 'laravel.test',
        'php', 'artisan', 'migrate:fresh', '--seed', '--force', '--env=e2e',
    ], `preparar o banco ${banco}`);

    console.log(`E2E: banco ${banco} recriado e semeado.`);
}
