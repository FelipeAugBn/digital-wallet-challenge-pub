{{-- Os itens já chegam traduzidos pelo controller: aqui só se desenha. --}}
@if ($itens->isEmpty())
    <p class="vazio">{{ $vazio }}</p>
@else
    <ul class="extrato">
        @php($diaAberto = null)

        @foreach ($itens as $item)
            {{-- O dia vira cabeçalho quando muda, como num extrato de banco. --}}
            @if ($item->dayKey !== $diaAberto)
                @php($diaAberto = $item->dayKey)
                <li class="dia">{{ $item->dayLabel }}</li>
            @endif

            @php($estornada = $item->status === 'Estornada')
            <li @class(['estornada' => $estornada])>
                <span class="descricao">
                    {{ $item->label }}
                    <span class="meta">
                        <span>{{ $item->time }}</span>
                        <span @class(['situacao', 'estornada' => $estornada])>{{ $item->status }}</span>
                    </span>
                </span>
                <span @class(['valor', 'entrada' => $item->isCredit])>{{ $item->amount }}</span>
                @if ($item->reversibleId)
                    <form class="estorno" method="POST" action="{{ route('reversals.store', $item->reversibleId) }}">
                        @csrf
                        <button type="submit">Estornar</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
@endif
