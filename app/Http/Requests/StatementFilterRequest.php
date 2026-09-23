<?php

namespace App\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * O periodo que a pessoa pediu no extrato.
 *
 * Os dois campos sao independentes: so a data inicial, so a final ou as duas.
 * Nenhum deles decide o que a pessoa pode ver; quem responde por isso e o
 * controller, que continua tirando a carteira de quem esta autenticado. Aqui so
 * se decide o que e uma data aceitavel e em que instante cada ponta cai.
 */
class StatementFilterRequest extends FormRequest
{
    /** O formato que o campo de data do navegador envia. */
    private const FORMATO = 'Y-m-d';

    /**
     * Recusado o periodo, a pessoa volta ao extrato sem filtro.
     *
     * A tela e a mesma, com a mensagem no topo e as datas de volta nos campos:
     * nao existe pagina de erro separada para um filtro, e voltar para a
     * pagina anterior levaria a pessoa a qualquer lugar de onde ela veio.
     *
     * @var string
     */
    protected $redirectRoute = 'statement';

    /**
     * Duas datas opcionais, e a final nunca antes da inicial.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'inicio' => ['nullable', 'date_format:'.self::FORMATO],
            'fim' => [
                'nullable',
                'date_format:'.self::FORMATO,
                // A comparacao so entra quando ha um inicio legivel para
                // comparar: sem essa condicao, uma data inicial ilegivel faria
                // a tela acusar tambem a final, que a pessoa escreveu certo.
                ...($this->inicioEhData() ? ['after_or_equal:inicio'] : []),
            ],
        ];
    }

    /**
     * Mensagens em portugues, no vocabulario da tela.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inicio.date_format' => 'Informe a data inicial no formato AAAA-MM-DD.',
            'fim.date_format' => 'Informe a data final no formato AAAA-MM-DD.',
            'fim.after_or_equal' => 'A data final não pode ser anterior à data inicial.',
        ];
    }

    /** O primeiro instante do dia inicial, ou nada quando nao houve filtro. */
    public function inicio(): ?Carbon
    {
        return $this->dia('inicio')?->startOfDay();
    }

    /**
     * O limite de cima, exclusivo: o primeiro instante do dia seguinte.
     *
     * O dia final entra inteiro. Fechar o intervalo no fim do dia obrigaria a
     * escolher uma ultima fracao de segundo e a torcer para que a precisao da
     * coluna nunca mude; comparar com `<` contra a meia-noite seguinte vale em
     * qualquer precisao e nao deixa lancamento nenhum de fora.
     */
    public function limiteFinal(): ?Carbon
    {
        return $this->dia('fim')?->startOfDay()->addDay();
    }

    /**
     * Os filtros aceitos, do jeito que voltam na URL das outras paginas.
     *
     * So o que passou pela validacao entra aqui, entao os links de pagina
     * carregam o periodo e mais nada do que veio na URL.
     *
     * @return array<string, string>
     */
    public function filtros(): array
    {
        return array_filter([
            'inicio' => $this->validated('inicio'),
            'fim' => $this->validated('fim'),
        ], fn (?string $valor) => $valor !== null);
    }

    /**
     * Se `inicio` chegou como uma data que da para comparar.
     *
     * A conferencia e a mesma do `date_format`: a data tem de sobreviver a ida
     * e a volta pelo formato, o que descarta tanto o texto solto quanto o dia
     * que nao existe no calendario.
     */
    private function inicioEhData(): bool
    {
        $valor = $this->input('inicio');

        if (! is_string($valor)) {
            return false;
        }

        $data = DateTimeImmutable::createFromFormat('!'.self::FORMATO, $valor);

        return $data !== false && $data->format(self::FORMATO) === $valor;
    }

    /**
     * O dia validado do campo, ja como data.
     *
     * `createFromFormat` completa a hora que falta com a de agora, dai o dia
     * sair daqui cru e cada ponta escolher o instante que lhe cabe.
     */
    private function dia(string $campo): ?Carbon
    {
        $valor = $this->validated($campo);

        return $valor === null ? null : Carbon::createFromFormat(self::FORMATO, $valor);
    }
}
