<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aprova o campo de valor usando o proprio conversor de `Money`.
 *
 * A regra nao repete a expressao nem o teto: quem decide o que e um valor
 * valido continua sendo uma unica classe.
 */
final class MoneyAmount implements ValidationRule
{
    /** Recusa qualquer entrada que o conversor nao aceite. */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Money::tryFromInput($value) === null) {
            $fail('Informe um valor entre R$ 0,01 e R$ 1.000.000,00, no formato 1.000,50.');
        }
    }
}
