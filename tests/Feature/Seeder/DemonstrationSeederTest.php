<?php

use App\Enums\ReversalReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\StatementEntry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

require_once __DIR__.'/helpers.php';

/** O "hoje" destes testes: com ele fixo, cada data do cenário é conhecida. */
const HOJE_DA_DEMONSTRACAO = '2026-09-23 12:00:00';

// Esta pasta roda fora da transação do `RefreshDatabase` porque a aceitação da
// tarefa é o `wallet:check` passando no banco semeado, e ele precisa de uma
// transação de verdade no nível mais alto. O porquê completo está em
// `encerraTransacaoDoTeste()`.
//
// O relógio é fixado antes de semear: o cenário tem passado, e o passado só é
// conferível quando se sabe que dia é hoje.
beforeEach(function () {
    encerraTransacaoDoTeste();
    Carbon::setTestNow(HOJE_DA_DEMONSTRACAO);
    $this->seed();
});

// A limpeza e a volta do relógio rodam mesmo quando uma asserção falha.
afterEach(function () {
    limpaBancoDeTeste();
    Carbon::setTestNow();
});

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

    expect((clone $concluidas)->where('type', TransactionType::Deposit)->count())->toBe(6)
        ->and((clone $concluidas)->where('type', TransactionType::Transfer)->count())->toBe(16);
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
    // Vinte e quatro originais e dois estornos; o depósito tem um lado só, a
    // transferência tem dois, e o estorno repete a forma da operação desfeita.
    expect(Transaction::count())->toBe(26)
        ->and(WalletEntry::count())->toBe(44)
        // Transferência não cria nem destrói dinheiro, e o depósito desfeito
        // saiu de cena: o que sobra nas três carteiras é o que entrou e ficou.
        ->and((int) Wallet::query()->sum('balance'))->toBe(175_000);
});

test('wallet:check não encontra divergência no banco semeado', function () {
    $resultado = conferencia();

    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and($resultado['saida'])->toContain('conferem')
        ->and($resultado['saida'])->toContain('3 carteira(s)')
        ->and($resultado['saida'])->toContain('44 lançamento(s)');
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

test('devolve o relógio ao que era depois de semear', function () {
    // O `beforeEach` fixou a hora e semeou: o seeder não pode ter deixado o
    // próprio relógio para trás nem apagado o do teste.
    expect(Carbon::getTestNow()?->format('Y-m-d H:i:s'))->toBe(HOJE_DA_DEMONSTRACAO)
        ->and(now()->format('Y-m-d H:i:s'))->toBe(HOJE_DA_DEMONSTRACAO);

    // E sem relógio fixado, volta ao relógio real: nada fica congelado.
    limpaBancoDeTeste();
    Carbon::setTestNow();
    $this->seed();

    expect(Carbon::hasTestNow())->toBeFalse();
});

test('cria pessoas e carteiras antes de qualquer movimentação delas', function () {
    foreach (User::query()->with('wallet')->get() as $pessoa) {
        $carteira = $pessoa->wallet;
        $primeiroLancamento = WalletEntry::query()->where('wallet_id', $carteira->id)->min('created_at');
        $primeiraOperacao = Transaction::query()->where('initiated_by_user_id', $pessoa->id)->min('created_at');

        expect($pessoa->created_at->lessThanOrEqualTo($carteira->created_at))->toBeTrue($pessoa->email)
            ->and($carteira->created_at->lessThanOrEqualTo(Carbon::parse($primeiroLancamento)))->toBeTrue($pessoa->email)
            ->and($pessoa->created_at->lessThanOrEqualTo(Carbon::parse($primeiraOperacao)))->toBeTrue($pessoa->email);
    }
});

test('as datas do cenário são determinísticas', function () {
    $abertura = Carbon::parse(HOJE_DA_DEMONSTRACAO)->startOfDay()->subDays(DatabaseSeeder::DIA_DE_ABERTURA);

    // As pessoas nascem na manhã do dia de abertura; a primeira operação é o
    // depósito da Ana às 09:12 desse dia; a última é o estorno de ontem.
    $primeira = Transaction::query()->orderBy('created_at')->orderBy('id')->first();
    $ultima = Transaction::query()->orderByDesc('created_at')->orderByDesc('id')->first();

    expect(pessoaDemo('ana@wallet.test')->created_at->format('Y-m-d H:i:s'))->toBe($abertura->format('Y-m-d').' 08:00:00')
        ->and($primeira->created_at->format('Y-m-d H:i:s'))->toBe($abertura->format('Y-m-d').' 09:12:00')
        ->and($ultima->created_at->format('Y-m-d H:i:s'))->toBe('2026-09-22 09:35:00')
        // O cenário ocupa cinco semanas, e ninguém se move no futuro.
        ->and(Carbon::parse(WalletEntry::query()->max('created_at'))->lessThan(Carbon::parse(HOJE_DA_DEMONSTRACAO)))->toBeTrue();
});

test('Ana tem 19 lançamentos e o extrato dela tem duas páginas', function () {
    $ana = pessoaDemo('ana@wallet.test');
    $paginas = paginasDoExtrato($ana);

    expect(WalletEntry::query()->where('wallet_id', $ana->wallet->id)->count())->toBe(19)
        ->and($paginas[1])->toHaveCount(15)
        ->and($paginas[2])->toHaveCount(4);
});

test('as páginas do extrato da Ana não repetem nem omitem lançamento', function () {
    $ana = pessoaDemo('ana@wallet.test');
    $paginas = paginasDoExtrato($ana);

    // A referência é o próprio livro-razão, na ordem em que a tela promete.
    $esperado = WalletEntry::query()->where('wallet_id', $ana->wallet->id)
        ->with(StatementEntry::RELACOES)->orderByDesc('created_at')->orderByDesc('id')->get()
        ->map(fn (WalletEntry $entry) => StatementEntry::from($entry, $ana->wallet->id)->amount)
        ->all();

    expect(array_merge($paginas[1], $paginas[2]))->toBe($esperado);
});

test('lançamentos do mesmo dia ficam sob um único cabeçalho', function () {
    $ana = pessoaDemo('ana@wallet.test');
    $this->actingAs($ana);

    // Quatro lançamentos em 14 de setembro, quatro em 7, quatro em 30 de agosto:
    // um cabeçalho para cada dia, e a página vira exatamente numa virada de dia.
    expect(diasNaTela($this->get(route('statement'))->getContent()))
        ->toBe(['Ontem', '18 de setembro', '14 de setembro', '7 de setembro', '30 de agosto'])
        ->and(diasNaTela($this->get(route('statement', ['page' => 2]))->getContent()))
        ->toBe(['22 de agosto']);
});

test('o painel mostra quando foi a última movimentação', function () {
    $this->actingAs(pessoaDemo('ana@wallet.test'))->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Última movimentação ontem às 09:35');
});

test('os gráficos do painel da Ana contam as cinco semanas do cenário', function () {
    $html = $this->actingAs(pessoaDemo('ana@wallet.test'))->get(route('dashboard'))->assertOk()->getContent();

    // Seis dias com movimento, um rótulo para cada; em setembro saiu um pouco
    // mais do que entrou. A legenda lista cada ação com o sinal que teve na
    // carteira: o envio estornado sai em "Enviado" e volta em "Estornado".
    expect(rotulosDasBarras($html))->toBe(['22 ago', '30 ago', '7 set', '14 set', '18 set', '22 set'])
        ->and(centroDoAnel($html))->toBe(['51%', 'saiu'])
        ->and(legendaDoAnel($html))->toBe([
            'Depositado' => '+R$ 50,00',
            'Recebido' => '+R$ 209,75',
            'Enviado' => '-R$ 434,25',
            'Estornado' => '+R$ 150,00',
            'Líquido' => '-R$ 24,50',
        ])
        ->and(descricaoDoGrafico($html, 'curva-desc'))->toBe('O saldo era R$ 0,00 em 19 de agosto e termina em R$ 800,00 hoje.');
});
