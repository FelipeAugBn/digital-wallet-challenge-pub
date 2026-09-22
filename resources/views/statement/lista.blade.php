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
            </li>
        @endforeach
    </ul>
@endif
