<?php

namespace App\Console\Commands;

use App\Enums\WalletEntryType;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckWalletsCommand extends Command
{
    /** O saldo acumulado ate um lancamento nao bate com o `balance_after` dele. */
    private const NO_LANCAMENTO = 'saldo acumulado do lançamento (balance_after)';

    /** A soma do livro-razao da carteira nao bate com o saldo guardado nela. */
    private const NO_SALDO = 'saldo da carteira (wallets.balance)';

    protected $signature = 'wallet:check';

    protected $description = 'Confere se o saldo de cada carteira corresponde ao livro-razão';

    /**
     * Compara as duas fontes de verdade e relata as diferencas, sem tocar em nada.
     *
     * O saldo da carteira responde "quanto tem"; o livro-razao responde "como
     * chegou nesse numero". As duas existem de proposito, e este comando e o
     * unico lugar que pergunta se elas ainda contam a mesma historia.
     *
     * Corrigir automaticamente seria o erro mais caro possivel: apagaria a
     * evidencia de um defeito sem que ninguem soubesse que ele existiu. Por
     * isso o comando so le, e o PostgreSQL e quem garante isso.
     */
    public function handle(): int
    {
        $resultado = DB::transaction(function (): array {
            // Primeiro statement da transacao, antes de qualquer consulta: a
            // partir daqui todas as leituras enxergam o mesmo instante, mesmo
            // que alguem deposite ou estorne durante a conferencia. Sem isso, a
            // soma de uma carteira poderia vir de antes e o saldo de depois,
            // inventando uma divergencia que nunca existiu.
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Defesa em profundidade: com a transacao em somente leitura, uma
            // escrita acidental vira erro do banco em vez de dado corrompido.
            DB::statement('SET TRANSACTION READ ONLY');

            return $this->conferir();
        });

        if ($resultado['divergencias'] === []) {
            $this->info(sprintf(
                'Saldos e livro-razão conferem: %d carteira(s) e %d lançamento(s) conferidos.',
                $resultado['carteiras'],
                $resultado['lancamentos'],
            ));

            return self::SUCCESS;
        }

        foreach ($resultado['divergencias'] as $divergencia) {
            $this->error($this->linha($divergencia));
        }

        $this->line(sprintf(
            '%d divergência(s) encontrada(s). Nada foi corrigido: este comando apenas lê.',
            count($resultado['divergencias']),
        ));

        return self::FAILURE;
    }

    /**
     * Percorre o livro-razao uma vez so, acumulando por carteira.
     *
     * Os lancamentos vem ordenados por carteira e por `id` crescente, que e a
     * ordem em que os saldos foram confirmados: o lock da carteira dura ate o
     * commit, entao `id` maior significa saldo confirmado depois. E essa ordem
     * que torna o `balance_after` verificavel.
     *
     * Tudo em centavos inteiros. Nenhuma etapa vira ponto flutuante, porque um
     * comando que existe para achar diferenca de um centavo nao pode ser a
     * origem dela.
     *
     * @return array{divergencias: list<array{carteira: int, lancamento: int|null, tipo: string, esperado: int, encontrado: int}>, carteiras: int, lancamentos: int}
     */
    private function conferir(): array
    {
        $saldos = Wallet::query()->orderBy('id')->pluck('balance', 'id');

        $divergencias = [];
        $acumulado = [];
        $lancamentos = 0;

        foreach (WalletEntry::query()->orderBy('wallet_id')->orderBy('id')->cursor() as $entry) {
            $carteira = (int) $entry->wallet_id;
            $lancamentos++;

            $soma = ($acumulado[$carteira] ?? 0) + ($entry->type === WalletEntryType::Credit
                ? $entry->amount
                : -$entry->amount);

            $acumulado[$carteira] = $soma;

            if ($entry->balance_after !== $soma) {
                $divergencias[] = [
                    'carteira' => $carteira,
                    'lancamento' => (int) $entry->id,
                    'tipo' => self::NO_LANCAMENTO,
                    'esperado' => $soma,
                    'encontrado' => $entry->balance_after,
                ];
            }
        }

        // Carteira sem lancamento nenhum tambem e conferida: o livro-razao dela
        // soma zero, e o saldo guardado precisa ser zero tambem.
        foreach ($saldos as $carteira => $saldo) {
            $livro = $acumulado[(int) $carteira] ?? 0;

            if ((int) $saldo !== $livro) {
                $divergencias[] = [
                    'carteira' => (int) $carteira,
                    'lancamento' => null,
                    'tipo' => self::NO_SALDO,
                    'esperado' => $livro,
                    'encontrado' => (int) $saldo,
                ];
            }
        }

        // Agrupa por carteira para que o relatorio possa ser lido de cima para
        // baixo; dentro de cada uma, os lancamentos antes do total.
        usort($divergencias, fn (array $a, array $b) => [$a['carteira'], $a['lancamento'] ?? PHP_INT_MAX]
            <=> [$b['carteira'], $b['lancamento'] ?? PHP_INT_MAX]);

        return [
            'divergencias' => $divergencias,
            'carteiras' => $saldos->count(),
            'lancamentos' => $lancamentos,
        ];
    }

    /**
     * Escreve uma divergencia na linha que o operador vai ler.
     *
     * Carteira e lancamento aparecem pelo id porque sao eles que permitem ir
     * ate a linha no banco; os valores saem formatados em reais e tambem em
     * centavos, que e como estao gravados.
     *
     * @param  array{carteira: int, lancamento: int|null, tipo: string, esperado: int, encontrado: int}  $divergencia
     */
    private function linha(array $divergencia): string
    {
        $onde = 'Carteira '.$divergencia['carteira'];

        if ($divergencia['lancamento'] !== null) {
            $onde .= ' · lançamento '.$divergencia['lancamento'];
        }

        return sprintf(
            '%s · %s — esperado %s (%d), encontrado %s (%d)',
            $onde,
            $divergencia['tipo'],
            Money::fromCents($divergencia['esperado'])->format(),
            $divergencia['esperado'],
            Money::fromCents($divergencia['encontrado'])->format(),
            $divergencia['encontrado'],
        );
    }
}
