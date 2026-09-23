<?php

namespace App\Http\Controllers;

use App\Actions\TransferMoney;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\RecipientNotFound;
use App\Exceptions\TransferToSelf;
use App\Http\Requests\TransferRequest;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransferController extends Controller
{
    /**
     * Abre o formulario com uma chave decidida aqui, nunca pelo navegador.
     *
     * O saldo vai junto so para a pessoa ver quanto tem antes de digitar; a
     * recusa por saldo insuficiente continua sendo decidida na Action, com a
     * carteira travada, e nunca a partir deste numero.
     */
    public function create(Request $request): View
    {
        return view('transfers.create', [
            'idempotencyKey' => $this->formKey(),
            'saldo' => Money::fromCents($request->user()->wallet()->firstOrFail()->balance)->format(),
        ]);
    }

    /**
     * Converte o valor validado, delega a operacao e redireciona.
     *
     * As recusas de negocio viram erro de campo: e-mail para os problemas de
     * destinatario, valor para o saldo. Nenhuma delas cita banco, constraint
     * ou identificador interno, e qualquer falha inesperada continua subindo.
     */
    public function store(TransferRequest $request, TransferMoney $transferMoney): RedirectResponse
    {
        $amount = Money::fromInput($request->validated('amount'));
        $recipientEmail = $request->validated('recipient_email');

        try {
            $transferMoney->handle(
                $request->user(),
                $recipientEmail,
                $amount,
                $request->validated('idempotency_key'),
            );
        } catch (RecipientNotFound|TransferToSelf $recusa) {
            throw ValidationException::withMessages(['recipient_email' => $recusa->getMessage()]);
        } catch (InsufficientFunds $recusa) {
            throw ValidationException::withMessages(['amount' => $recusa->getMessage()]);
        }

        return redirect()
            ->route('dashboard')
            ->with('sucesso', 'Transferência de '.$amount->format().' para '.$recipientEmail.' realizada.');
    }

    /**
     * A chave que o formulario vai levar.
     *
     * Depois de um erro de validacao a chave anterior volta, para que a
     * correcao continue sendo a mesma operacao e nao uma segunda. Se ela nao
     * veio, ou se veio adulterada, o servidor gera outra. A view so imprime o
     * que decidimos aqui.
     */
    private function formKey(): string
    {
        $anterior = old('idempotency_key');

        return is_string($anterior) && Str::isUuid($anterior)
            ? $anterior
            : (string) Str::uuid7();
    }
}
