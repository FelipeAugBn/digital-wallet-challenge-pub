<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    /**
     * Campos que podem ser preenchidos em massa.
     *
     * O saldo fica de fora: ele so muda por movimentacao financeira.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
    ];

    /**
     * Saldo em centavos: inteiro no PHP como e inteiro no banco.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    /** O dono da carteira, numa relacao de um para um. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Os lancamentos da carteira, que formam o extrato. */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }

    /** Operacoes em que esta carteira e a origem do dinheiro. */
    public function outgoingTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'source_wallet_id');
    }

    /** Operacoes em que esta carteira e o destino do dinheiro. */
    public function incomingTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'destination_wallet_id');
    }
}
