<?php

namespace App\Models;

use App\Enums\ReversalReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    /** Gera o UUIDv7 no servidor e trata a chave como string nao incremental. */
    use HasUuids;

    /**
     * Campos que uma Action financeira preenche.
     *
     * O `id` fica de fora: quem o gera e o trait, nunca a requisicao.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'status',
        'amount',
        'initiated_by_user_id',
        'source_wallet_id',
        'destination_wallet_id',
        'original_transaction_id',
        'reversal_reason',
        'idempotency_key',
    ];

    /**
     * Enums do dominio na leitura e valor em centavos como inteiro.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'reversal_reason' => ReversalReason::class,
            'amount' => 'integer',
        ];
    }

    /** Quem pediu a operacao; nulo apenas no estorno automatico. */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /** Carteira de onde o dinheiro saiu; vazia no deposito. */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    /** Carteira para onde o dinheiro foi; vazia no estorno de deposito. */
    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    /** A operacao que este estorno anula. */
    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    /** O estorno que anulou esta operacao, quando existir; no maximo um. */
    public function reversal(): HasOne
    {
        return $this->hasOne(Transaction::class, 'original_transaction_id');
    }

    /** Os lancamentos gerados aqui: um por carteira afetada. */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }
}
