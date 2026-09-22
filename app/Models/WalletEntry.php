<?php

namespace App\Models;

use App\Enums\WalletEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletEntry extends Model
{
    /**
     * Campos que uma Action financeira preenche.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'wallet_id',
        'type',
        'amount',
        'balance_after',
    ];

    /**
     * Valor e saldo em centavos; o saldo aceita negativo por causa do estorno.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletEntryType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /** A operacao que originou o lancamento. */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** A carteira à qual o lançamento pertence. */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
