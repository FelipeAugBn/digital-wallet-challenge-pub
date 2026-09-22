<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cria a tabela e as regras que o banco passa a cobrar sozinho. */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('status')->default('completed');
            $table->bigInteger('amount');
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('source_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->foreignId('destination_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->uuid('original_transaction_id')->nullable()->unique();
            $table->string('reversal_reason')->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->timestamps();

            $table->index('initiated_by_user_id');
            $table->index('source_wallet_id');
            $table->index('destination_wallet_id');
        });

        // A referencia para a propria tabela so pode ser criada depois da chave primaria.
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('original_transaction_id')->references('id')->on('transactions')->restrictOnDelete();
        });

        DB::statement('alter table transactions add constraint transactions_amount_positive check (amount > 0)');
        DB::statement("alter table transactions add constraint transactions_type_valid check (type in ('deposit', 'transfer', 'reversal'))");
        DB::statement("alter table transactions add constraint transactions_status_valid check (status in ('completed', 'reversed'))");
        DB::statement("alter table transactions add constraint transactions_reversal_reason_valid check (reversal_reason is null or reversal_reason in ('user_request', 'inconsistency'))");
        // Um estorno automatico nao tem autor; qualquer outra operacao tem.
        DB::statement("alter table transactions add constraint transactions_initiator_required check (initiated_by_user_id is not null or type = 'reversal')");

        // Parcial e por usuario: a mesma chave pode se repetir entre pessoas diferentes.
        DB::statement('create unique index transactions_user_idempotency_key_unique on transactions (initiated_by_user_id, idempotency_key) where idempotency_key is not null');
    }

    /** Derruba a tabela; constraints e indices caem junto com ela. */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
