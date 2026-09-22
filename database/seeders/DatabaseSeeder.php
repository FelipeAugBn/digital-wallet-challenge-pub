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
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** A senha das tres contas: ficticia, igual para todas e facil de digitar. */
    public const SENHA = 'demonstracao';

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
        $ana = $this->pessoa($createWallet, 'Ana Ribeiro', 'ana@wallet.test');
        $bruno = $this->pessoa($createWallet, 'Bruno Carvalho', 'bruno@wallet.test');
        $carla = $this->pessoa($createWallet, 'Carla Nogueira', 'carla@wallet.test');

        // Entrada de dinheiro: e o unico jeito de o saldo nascer.
        $this->deposita($depositMoney, $ana, '1.000,00');
        $this->deposita($depositMoney, $bruno, '500,00');
        $this->deposita($depositMoney, $carla, '250,00');

        // Dinheiro circulando entre as tres carteiras.
        $this->transfere($transferMoney, $ana, $bruno, '200,00');
        $this->transfere($transferMoney, $bruno, $carla, '100,00');

        // Transferencia que Ana desfaz pela propria tela. O motivo exige autor,
        // e o autor precisa ser quem iniciou a operacao.
        $enganoDaAna = $this->transfere($transferMoney, $ana, $carla, '150,00');
        $reverseTransaction->handle($enganoDaAna->id, ReversalReason::UserRequest, $ana);

        // Deposito lancado em duplicidade e desfeito pelo operador, sem autor:
        // e o caso que o comando `wallet:reverse` atende.
        $depositoEmDuplicidade = $this->deposita($depositMoney, $carla, '80,00');
        $reverseTransaction->handle($depositoEmDuplicidade->id, ReversalReason::Inconsistency, null);

        $this->apresentaCredenciais([$ana, $bruno, $carla]);
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
