<?php

use App\Enums\ReversalReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

require_once __DIR__.'/helpers.php';

// Esta pasta roda fora da transação do `RefreshDatabase` porque a aceitação da
// tarefa é o `wallet:check` passando no banco semeado, e ele precisa de uma
// transação de verdade no nível mais alto. O porquê completo está em
// `encerraTransacaoDoTeste()`.
beforeEach(function () {
    encerraTransacaoDoTeste();
    $this->seed();
});

afterEach(fn () => limpaBancoDeTeste());

test('semeia exatamente três pessoas, cada uma com sua carteira', function () {
    expect(User::count())->toBe(3)
        ->and(Wallet::count())->toBe(3)
        // Uma carteira por pessoa, e nenhuma órfã.
        ->and(Wallet::query()->distinct()->count('user_id'))->toBe(3);
});

test('as credenciais da demonstração entram na aplicação', function (string $email) {
    $resposta = $this->post('/login', ['email' => $email, 'password' => DatabaseSeeder::SENHA]);

    $resposta->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs(pessoaDemo($email));
})->with(['ana@wallet.test', 'bruno@wallet.test', 'carla@wallet.test']);

test('o cenário tem depósitos e transferências concluídos', function () {
    $concluidas = Transaction::query()->where('status', TransactionStatus::Completed);

    expect((clone $concluidas)->where('type', TransactionType::Deposit)->count())->toBe(3)
        ->and((clone $concluidas)->where('type', TransactionType::Transfer)->count())->toBe(2);
});

test('tem uma operação desfeita a pedido de quem a iniciou', function () {
    $estornadas = estornadasPor(ReversalReason::UserRequest->value);

    expect($estornadas)->toHaveCount(1);

    $original = $estornadas->first();

    // A transferência de Ana para Carla: quem pediu o estorno é quem a iniciou.
    expect($original->type)->toBe(TransactionType::Transfer)
        ->and($original->amount)->toBe(15_000)
        ->and($original->reversal->initiated_by_user_id)->toBe($original->initiated_by_user_id);
});

test('tem uma operação desfeita por inconsistência', function () {
    $estornadas = estornadasPor(ReversalReason::Inconsistency->value);

    expect($estornadas)->toHaveCount(1);

    $original = $estornadas->first();

    // O depósito em duplicidade de Carla, desfeito pelo operador: sem autor,
    // como acontece quando o gatilho é o comando e não a tela.
    expect($original->type)->toBe(TransactionType::Deposit)
        ->and($original->amount)->toBe(8_000)
        ->and($original->reversal->initiated_by_user_id)->toBeNull();
});

test('os saldos finais são os que a demonstração anuncia', function () {
    expect(saldoDemo('ana@wallet.test'))->toBe(80_000)
        ->and(saldoDemo('bruno@wallet.test'))->toBe(60_000)
        ->and(saldoDemo('carla@wallet.test'))->toBe(35_000);
});

test('o dinheiro do cenário fecha com o que entrou e ficou de pé', function () {
    // Sete originais e dois estornos; o depósito tem um lado só, a
    // transferência tem dois, e o estorno repete a forma da operação desfeita.
    expect(Transaction::count())->toBe(9)
        ->and(WalletEntry::count())->toBe(13)
        // Transferência não cria nem destrói dinheiro, e o depósito desfeito
        // saiu de cena: o que sobra nas três carteiras é o que entrou e ficou.
        ->and((int) Wallet::query()->sum('balance'))->toBe(175_000);
});

test('wallet:check não encontra divergência no banco semeado', function () {
    $resultado = conferencia();

    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and($resultado['saida'])->toContain('conferem')
        ->and($resultado['saida'])->toContain('3 carteira(s)')
        ->and($resultado['saida'])->toContain('13 lançamento(s)');
});

test('as três pessoas veem a própria movimentação no extrato', function () {
    $ana = $this->actingAs(pessoaDemo('ana@wallet.test'))->get(route('statement'));

    $ana->assertOk()
        ->assertSee('Transferência enviada para Bruno Carvalho')
        ->assertSee('Estorno de transferência enviada para Carla Nogueira')
        ->assertSee('Estornada');

    $bruno = $this->actingAs(pessoaDemo('bruno@wallet.test'))->get(route('statement'));

    $bruno->assertOk()
        ->assertSee('Transferência recebida de Ana Ribeiro')
        ->assertSee('Transferência enviada para Carla Nogueira');

    $carla = $this->actingAs(pessoaDemo('carla@wallet.test'))->get(route('statement'));

    $carla->assertOk()
        ->assertSee('Estorno de depósito')
        ->assertSee('Transferência recebida de Bruno Carvalho');
});

test('semear de novo produz exatamente o mesmo cenário', function () {
    $primeira = retratoDaDemonstracao();

    limpaBancoDeTeste();
    $this->seed();

    // Identificadores e chaves mudam por natureza; o que a demonstração mostra
    // — pessoas, valores, estados e saldos — não pode mudar.
    expect(retratoDaDemonstracao())->toBe($primeira);
});
