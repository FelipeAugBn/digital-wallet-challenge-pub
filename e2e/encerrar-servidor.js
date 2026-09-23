import { execFileSync } from 'node:child_process';

/** O nome fixo do container do E2E, para achá-lo sem adivinhação. */
export const CONTAINER = 'wallet-e2e';

/**
 * Derruba o servidor do E2E ao fim da execução.
 *
 * `docker compose run` nem sempre leva o container embora quando o processo
 * morre, e um container esquecido segurando a porta faria a próxima execução
 * parar antes de começar. Por isso o nome é fixo: dá para encerrá-lo sem
 * procurar.
 */
export default function encerraServidor() {
    try {
        execFileSync('docker', ['rm', '--force', CONTAINER], { stdio: 'ignore' });
    } catch {
        // Já não existia, que é o caso comum.
    }
}
