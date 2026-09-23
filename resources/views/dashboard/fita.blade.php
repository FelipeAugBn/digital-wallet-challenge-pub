{{-- A fita do painel: hora, o que foi, o valor com sinal e o saldo que ficou.
     Os itens já chegam traduzidos pelo controller; aqui só se desenha. --}}
@if ($itens->isEmpty())
    <p class="vazio">{{ $vazio }}</p>
@else
    <ul class="fita-lista">
        @foreach ($itens as $item)
            @php($estornada = $item->status === 'Estornada')
            <li @class(['entrada' => $item->isCredit, 'saida' => ! $item->isCredit, 'estorno' => $item->isReversal || $estornada, 'estornada' => $estornada])>
                <span class="hora">{{ $item->dayShort }} {{ $item->time }}</span>
                <span class="rot">{{ $item->label }}@if ($estornada) <em>estornada</em>@endif</span>
                <span @class(['valor', 'entrada' => $item->isCredit])>{{ $item->amount }}</span>
                <span class="pos"><span class="pos-rotulo">saldo</span> {{ $item->balanceAfter }}</span>
            </li>
        @endforeach
    </ul>
@endif
