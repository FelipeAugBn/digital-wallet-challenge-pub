<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cria o livro-razao: uma linha por carteira afetada em cada operacao. */
    public function up(): void
    {
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->bigInteger('amount');
            // Assinado: o estorno pode deixar a carteira negativa.
            $table->bigInteger('balance_after');
            $table->timestamps();

            $table->unique(['transaction_id', 'wallet_id', 'type']);
        });

        DB::statement('alter table wallet_entries add constraint wallet_entries_amount_positive check (amount > 0)');
        DB::statement("alter table wallet_entries add constraint wallet_entries_type_valid check (type in ('credit', 'debit'))");

        // Ordem exata da consulta do extrato.
        DB::statement('create index wallet_entries_statement_index on wallet_entries (wallet_id, created_at desc, id desc)');
    }

    /** Derruba a tabela; constraints e indices caem junto com ela. */
    public function down(): void
    {
        Schema::dropIfExists('wallet_entries');
    }
};
