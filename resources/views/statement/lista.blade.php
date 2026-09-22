{{-- Os itens já chegam traduzidos pelo controller: aqui só se desenha. --}}
@if ($itens->isEmpty())
    <p class="vazio">{{ $vazio }}</p>
@else
    <ul class="extrato">
        @foreach ($itens as $item)
            <li>
                <span class="descricao">
                    {{ $item->label }}
                    <span class="meta">
                        {{ $item->date }} ·
                        <span class="situacao @if ($item->status === 'Estornada') estornada @endif">{{ $item->status }}</span>
                    </span>
                </span>
                <span class="valor @if ($item->isCredit) entrada @endif">{{ $item->amount }}</span>
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
