<?php

namespace Database\Seeders;

use App\Actions\CreateWallet;
use App\Actions\DepositMoney;
use App\Actions\ReverseTransaction;
use App\Actions\TransferMoney;
use App\Enums\ReversalReason;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Monta o cenario de demonstracao: tres pessoas e um extrato com todos os
 * estados que a aplicacao sabe produzir.
 *
 * Nada e inserido direto nas tabelas financeiras. Cada movimentacao passa pela
 * mesma Action que a interface chama, pelos mesmos locks e pela mesma
 * transacao, e por isso os saldos daqui sao tao confiaveis quanto os de quem
 * usa o sistema. E o que faz `wallet:check` terminar sem divergencia no banco
 * semeado: se o seeder escrevesse por fora, a conferencia acusaria na hora.
 *
 * Nenhum valor e sorteado. Os nomes, os e-mails, a senha e as quantias sao
 * fixos para que a demonstracao seja sempre a mesma e os saldos finais possam
 * ser conferidos de cabeca.
 *
 * O cenario tem passado: as operacoes acontecem ao longo das ultimas cinco
 * semanas, e nao todas no mesmo instante. Isso nao muda a regra de quem grava
 * — quem grava continua sendo a Action. O que se move e o relogio, em `em()`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** A senha das tres contas: ficticia, igual para todas e facil de digitar. */
    public const SENHA = 'demonstracao';

    /**
     * As cinco semanas de movimentacao das tres contas, da mais antiga para a
     * mais recente.
     *
     * Cada linha e "dias atras, hora, operacao, de, para, valor". As duas
     * operacoes que terminam desfeitas ficam fora daqui, no fim de `run()`,
     * porque cada uma precisa da transacao que ela anula.
     *
     * O ritmo e o de uma carteira em uso: Ana movimenta quase todo dia, com um
     * deposito pequeno por semana e transferencias miudas para os dois; Bruno
     * e Carla aparecem dia sim, dia nao. E o que da aos graficos do painel um
     * mes inteiro de pontos, e nao meia duzia.
     *
     * As quantias fecham exatamente nos saldos anunciados no README, e nenhuma
     * carteira passa por saldo negativo no caminho.
     *
     * @var list<array{int, string, string, string, ?string, string}>
     */
    private const HISTORICO = [
        // Um dia de abertura: cada uma poe o proprio dinheiro e o primeiro
        // dinheiro comeca a circular.
        [self::DIA_DE_ABERTURA, '09:12', 'deposito', 'ana', null, '1.000,00'],
        [self::DIA_DE_ABERTURA, '10:40', 'deposito', 'bruno', null, '250,00'],
        [self::DIA_DE_ABERTURA, '11:05', 'deposito', 'carla', null, '90,00'],
        [self::DIA_DE_ABERTURA, '14:20', 'transferencia', 'ana', 'bruno', '200,00'],
        [self::DIA_DE_ABERTURA, '15:48', 'transferencia', 'bruno', 'carla', '100,00'],
        [self::DIA_DE_ABERTURA, '19:47', 'transferencia', 'ana', 'carla', '45,50'],
        [self::DIA_DE_ABERTURA, '20:15', 'transferencia', 'carla', 'ana', '20,00'],

        [31, '09:40', 'transferencia', 'ana', 'bruno', '30,00'],
        [30, '12:15', 'transferencia', 'carla', 'ana', '15,00'],
        [29, '08:20', 'deposito', 'ana', null, '60,00'],
        [29, '18:05', 'transferencia', 'ana', 'carla', '40,00'],
        [28, '10:10', 'transferencia', 'bruno', 'ana', '25,00'],
        [27, '14:30', 'transferencia', 'ana', 'bruno', '55,00'],
        [26, '09:05', 'transferencia', 'ana', 'carla', '18,50'],
        [25, '11:45', 'transferencia', 'carla', 'bruno', '60,00'],

        [24, '08:30', 'deposito', 'ana', null, '50,00'],
        [24, '09:22', 'transferencia', 'ana', 'bruno', '90,00'],
        [24, '12:41', 'transferencia', 'bruno', 'ana', '60,00'],
        [24, '18:05', 'transferencia', 'ana', 'carla', '120,00'],
        [24, '19:30', 'transferencia', 'carla', 'bruno', '90,00'],
        [22, '10:30', 'deposito', 'bruno', null, '100,00'],
        [22, '13:15', 'transferencia', 'bruno', 'ana', '70,00'],
        [21, '09:50', 'transferencia', 'ana', 'bruno', '33,00'],
        [20, '15:20', 'transferencia', 'ana', 'carla', '27,75'],
        [19, '11:00', 'transferencia', 'carla', 'ana', '80,50'],
        [18, '08:45', 'deposito', 'ana', null, '40,00'],
        [18, '17:30', 'transferencia', 'ana', 'bruno', '65,00'],
        [17, '12:20', 'transferencia', 'bruno', 'carla', '45,00'],

        [16, '08:05', 'deposito', 'bruno', null, '60,00'],
        [16, '10:14', 'transferencia', 'ana', 'bruno', '35,00'],
        [16, '13:44', 'transferencia', 'bruno', 'ana', '35,00'],
        [16, '16:20', 'transferencia', 'ana', 'carla', '75,25'],
        [16, '17:02', 'transferencia', 'carla', 'ana', '12,75'],
        [15, '10:05', 'transferencia', 'ana', 'carla', '30,00'],
        [14, '09:30', 'transferencia', 'ana', 'bruno', '48,00'],
        [13, '14:10', 'transferencia', 'carla', 'ana', '68,00'],
        [12, '08:15', 'deposito', 'ana', null, '50,00'],
        [12, '19:00', 'transferencia', 'ana', 'carla', '52,00'],
        [11, '11:25', 'transferencia', 'bruno', 'ana', '139,50'],
        [10, '16:40', 'transferencia', 'ana', 'bruno', '41,50'],

        [9, '09:15', 'transferencia', 'bruno', 'ana', '140,00'],
        [9, '11:50', 'transferencia', 'ana', 'carla', '64,00'],
        [9, '15:25', 'transferencia', 'carla', 'ana', '22,00'],
        [9, '18:30', 'deposito', 'ana', null, '30,00'],
        [8, '10:00', 'transferencia', 'ana', 'carla', '36,00'],
        [7, '13:35', 'transferencia', 'carla', 'bruno', '70,00'],
        [6, '09:10', 'transferencia', 'ana', 'bruno', '58,00'],
        [5, '14:05', 'transferencia', 'ana', 'bruno', '110,00'],
        [4, '11:15', 'transferencia', 'bruno', 'ana', '200,00'],
        [3, '15:50', 'transferencia', 'ana', 'carla', '44,25'],
        [2, '09:00', 'deposito', 'ana', null, '20,00'],
        [2, '17:25', 'transferencia', 'ana', 'bruno', '19,00'],
    ];

    /** Quantos dias antes de hoje o cenario abre: as pessoas nascem nesse dia. */
    public const DIA_DE_ABERTURA = 32;

    /** O dia em que a demonstracao foi semeada, de onde sai todo o passado. */
    private Carbon $hoje;

    /**
     * Semeia o banco com o cenario inteiro.
     *
     * As Actions chegam pelo container, como chegam ao Controller. Pedir as
     * quatro aqui deixa visivel, na assinatura, que este arquivo nao sabe
     * movimentar dinheiro sozinho.
     */
    public function run(
        CreateWallet $createWallet,
        DepositMoney $depositMoney,
        TransferMoney $transferMoney,
        ReverseTransaction $reverseTransaction,
    ): void {
        $this->hoje = Carbon::today();
        $relogioAnterior = Carbon::getTestNow();

        try {
            // As pessoas nascem antes do primeiro lancamento, na manha do dia de
            // abertura: ninguem pode movimentar uma carteira que ainda nao existe.
            $this->em(self::DIA_DE_ABERTURA, '08:00');

            $pessoas = [
                'ana' => $this->pessoa($createWallet, 'Ana Ribeiro', 'ana@wallet.test'),
                'bruno' => $this->pessoa($createWallet, 'Bruno Carvalho', 'bruno@wallet.test'),
                'carla' => $this->pessoa($createWallet, 'Carla Nogueira', 'carla@wallet.test'),
            ];

            foreach (self::HISTORICO as [$dias, $hora, $tipo, $de, $para, $valor]) {
                $this->em($dias, $hora);

                $tipo === 'deposito'
                    ? $this->deposita($depositMoney, $pessoas[$de], $valor)
                    : $this->transfere($transferMoney, $pessoas[$de], $pessoas[$para], $valor);
            }

            // Deposito lancado em duplicidade e desfeito pelo operador, sem autor:
            // e o caso que o comando `wallet:reverse` atende.
            $this->em(5, '16:40');
            $depositoEmDuplicidade = $this->deposita($depositMoney, $pessoas['carla'], '80,00');

            $this->em(5, '17:10');
            $reverseTransaction->handle($depositoEmDuplicidade->id, ReversalReason::Inconsistency, null);

            // Transferencia que Ana desfaz pela propria tela. O motivo exige autor,
            // e o autor precisa ser quem iniciou a operacao.
            $this->em(1, '09:20');
            $enganoDaAna = $this->transfere($transferMoney, $pessoas['ana'], $pessoas['carla'], '150,00');

            $this->em(1, '09:35');
            $reverseTransaction->handle($enganoDaAna->id, ReversalReason::UserRequest, $pessoas['ana']);
        } finally {
            // O relogio volta ao que era mesmo se alguma operacao falhar: deixar
            // a aplicacao com a hora congelada seria pior que nao semear. Volta
            // ao que era, e nao ao real, porque um teste pode ter fixado a hora
            // antes de semear e continua precisando dela depois.
            Carbon::setTestNow($relogioAnterior);
        }

        $this->apresentaCredenciais(array_values($pessoas));
    }

    /**
     * Move o relogio da aplicacao para o instante desta operacao.
     *
     * O seeder continua sem tocar nas tabelas financeiras: quem grava e a
     * Action, e e ela que carimba `created_at` com a hora corrente. Mover o
     * relogio e o unico jeito de dar passado ao cenario sem escrever por fora.
     */
    private function em(int $diasAtras, string $hora): void
    {
        Carbon::setTestNow($this->hoje->copy()->subDays($diasAtras)->setTimeFromTimeString($hora));
    }

    /**
     * Cria a pessoa pela factory e a carteira pela mesma Action do cadastro.
     *
     * A factory continua responsavel pelo formato da linha; o que muda aqui sao
     * os campos que a demonstracao precisa ter fixos. A senha vai em texto
     * puro de proposito: o cast `hashed` do Model a transforma na gravacao,
     * como acontece no cadastro pela tela.
     */
    private function pessoa(CreateWallet $createWallet, string $nome, string $email): User
    {
        $user = User::factory()->create([
            'name' => $nome,
            'email' => $email,
            'password' => self::SENHA,
        ]);

        $createWallet->handle($user);

        return $user;
    }

    /** Um deposito igual ao que o formulario faz, com chave nova a cada chamada. */
    private function deposita(DepositMoney $depositMoney, User $user, string $valor): Transaction
    {
        return $depositMoney->handle($user, Money::fromInput($valor), $this->chave());
    }

    /** Uma transferencia igual a da tela: o destino e identificado pelo e-mail. */
    private function transfere(TransferMoney $transferMoney, User $de, User $para, string $valor): Transaction
    {
        return $transferMoney->handle($de, $para->email, Money::fromInput($valor), $this->chave());
    }

    /**
     * A chave de idempotencia de uma operacao do cenario.
     *
     * E o mesmo gerador do Controller. Sortear aqui nao deixa a demonstracao
     * imprevisivel: a chave nao escolhe valor nem participante, so impede que
     * a mesma operacao entre duas vezes.
     */
    private function chave(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * Mostra as credenciais e os saldos com que a demonstracao comeca.
     *
     * Os saldos sao lidos do banco, e nao repetidos de uma conta feita aqui:
     * o que aparece na tabela e o que a aplicacao gravou de verdade.
     *
     * @param  list<User>  $pessoas
     */
    private function apresentaCredenciais(array $pessoas): void
    {
        if (! isset($this->command)) {
            return;
        }

        $linhas = [];

        foreach ($pessoas as $pessoa) {
            $saldo = (int) $pessoa->wallet()->value('balance');

            $linhas[] = [$pessoa->name, $pessoa->email, self::SENHA, Money::fromCents($saldo)->format()];
        }

        $this->command->newLine();
        $this->command->info('Contas de demonstração — a senha é a mesma para as três.');
        $this->command->table(['Pessoa', 'E-mail', 'Senha', 'Saldo'], $linhas);
    }
}
