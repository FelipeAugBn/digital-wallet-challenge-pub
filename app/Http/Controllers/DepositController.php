<?php

namespace App\Http\Controllers;

use App\Actions\DepositMoney;
use App\Http\Requests\DepositRequest;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DepositController extends Controller
{
    /**
     * Abre o formulario com uma chave nova, gerada aqui e nunca pelo navegador.
     *
     * O saldo vai junto so para a pessoa ver de onde parte; a regra que decide
     * se a operacao cabe continua na Action, com a carteira travada.
     */
    public function create(Request $request): View
    {
        return view('deposits.create', [
            'idempotencyKey' => (string) Str::uuid7(),
            'saldo' => Money::fromCents($request->user()->wallet()->firstOrFail()->balance)->format(),
        ]);
    }

    /**
     * Converte o valor validado, delega a operacao e redireciona.
     *
     * O redirect depois do POST evita que atualizar a pagina reenvie o
     * deposito; se a pessoa reenviar mesmo assim, a chave barra a repeticao.
     */
    public function store(DepositRequest $request, DepositMoney $depositMoney): RedirectResponse
    {
        $amount = Money::fromInput($request->validated('amount'));

        $depositMoney->handle($request->user(), $amount, $request->validated('idempotency_key'));

        return redirect()
            ->route('dashboard')
            ->with('sucesso', 'Depósito de '.$amount->format().' realizado.');
    }
}
