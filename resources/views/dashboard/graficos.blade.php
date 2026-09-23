{{-- Os três gráficos do painel, cada um no seu cartão da grade. Todo número
     desenhado também está em texto: título e descrição de cada SVG, rótulos
     de eixo e a legenda do anel. --}}

<section class="cartao dial-caixa">
    <h2>{{ $graficos->mes }} <span>entrou × saiu</span></h2>

    <svg viewBox="0 0 200 200" class="dial" role="img" aria-labelledby="anel-titulo anel-desc">
        <title id="anel-titulo">{{ $graficos->mes }}: o que entrou e o que saiu</title>
        <desc id="anel-desc">{{ $graficos->descricaoAnel }}</desc>
        @for ($i = 0; $i < 12; $i++)
            @php($a = deg2rad($i * 30 - 90))
            <line x1="{{ round(100 + 93 * cos($a), 1) }}" y1="{{ round(100 + 93 * sin($a), 1) }}" x2="{{ round(100 + 100 * cos($a), 1) }}" y2="{{ round(100 + 100 * sin($a), 1) }}" class="traco-dial"/>
        @endfor
        <g transform="rotate(-90 100 100)">
            <circle r="74" cx="100" cy="100" class="trilho"/>
            @unless ($graficos->mesVazio)
                <circle r="74" cx="100" cy="100" class="seg-entrou" stroke-dasharray="{{ $graficos->arcoEntrou }} 1000" stroke-dashoffset="-3"/>
                <circle r="74" cx="100" cy="100" class="seg-saiu" stroke-dasharray="{{ $graficos->arcoSaiu }} 1000" stroke-dashoffset="{{ $graficos->deslocamentoSaiu }}"/>
            @endunless
        </g>
        @if ($graficos->mesVazio)
            <text x="100" y="98" class="anel-percentual" text-anchor="middle">—</text>
            <text x="100" y="118" class="anel-lado" text-anchor="middle">sem movimento</text>
        @else
            <text x="100" y="98" class="anel-percentual" text-anchor="middle">{{ $graficos->percentual }}%</text>
            <text x="100" y="118" class="anel-lado" text-anchor="middle">{{ $graficos->lado }}</text>
        @endif
    </svg>

    <ul class="anel-legenda">
        @foreach ($graficos->legendaAnel as $linha)
            <li class="{{ $linha['classe'] }}"><span>{{ $linha['rotulo'] }}</span><b>{{ $linha['valor'] }}</b></li>
        @endforeach
    </ul>
</section>

<section class="cartao largo curva-caixa">
    <h2>Saldo nas últimas cinco semanas</h2>
    <p class="sub">Um ponto por dia com movimento.@if ($graficos->temEstorno) O ponto âmbar marca um estorno.@endif</p>

    <svg viewBox="0 0 {{ App\Support\DashboardCharts::CURVA_LARGURA }} {{ App\Support\DashboardCharts::CURVA_ALTURA }}" class="curva" role="img" aria-labelledby="curva-titulo curva-desc">
        <title id="curva-titulo">Saldo nas últimas cinco semanas</title>
        <desc id="curva-desc">{{ $graficos->descricaoCurva }}</desc>
        <defs>
            <linearGradient id="curva-area" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" class="area-topo" stop-opacity=".18"/>
                <stop offset="1" class="area-base" stop-opacity="0"/>
            </linearGradient>
        </defs>
        @foreach ($graficos->grade as $linha)
            <line x1="{{ $linha['x1'] }}" x2="{{ $linha['x2'] }}" y1="{{ $linha['y1'] }}" y2="{{ $linha['y2'] }}" class="{{ $linha['forte'] ? 'grade-forte' : 'grade' }}"/>
        @endforeach
        @foreach ($graficos->eixoY as $marca)
            <text x="{{ $marca['x'] }}" y="{{ $marca['y'] }}" class="eixo" text-anchor="end">{{ $marca['rotulo'] }}</text>
        @endforeach
        <path d="{{ $graficos->area }}" fill="url(#curva-area)"/>
        <path d="{{ $graficos->linha }}" class="traco"/>
        @foreach ($graficos->pontos as $ponto)
            <circle cx="{{ $ponto['x'] }}" cy="{{ $ponto['y'] }}" r="3.2" class="{{ $ponto['estorno'] ? 'marca-estorno' : 'marca-dia' }}"><title>{{ $ponto['titulo'] }}</title></circle>
        @endforeach
        <circle cx="{{ $graficos->fim['x'] }}" cy="{{ $graficos->fim['y'] }}" r="10" class="halo"/>
        <circle cx="{{ $graficos->fim['x'] }}" cy="{{ $graficos->fim['y'] }}" r="4" class="ponto-fim"/>
        <text x="{{ $graficos->fim['x'] + 16 }}" y="{{ $graficos->fim['y'] + 5 }}" class="rotulo-fim">{{ $graficos->saldoAtual }}</text>
        @foreach ($graficos->eixo as $marca)
            <text x="{{ $marca['x'] }}" y="{{ App\Support\DashboardCharts::CURVA_ALTURA - 10 }}" @class(['eixo', 'hoje' => $marca['rotulo'] === 'hoje']) text-anchor="{{ $marca['ancora'] }}">{{ $marca['rotulo'] }}</text>
        @endforeach
    </svg>
</section>

<section class="cartao barras-caixa">
    <h2>Entradas e saídas</h2>
    <p class="sub">Por dia com movimento; o trecho âmbar é a parte estornada</p>

    <svg viewBox="0 0 {{ App\Support\DashboardCharts::BARRAS_LARGURA }} {{ $graficos->barrasAltura }}" class="barras" role="img" aria-labelledby="barras-titulo barras-desc">
        <title id="barras-titulo">Entradas e saídas por dia</title>
        <desc id="barras-desc">{{ $graficos->descricaoBarras }}</desc>
        <line x1="12" x2="{{ App\Support\DashboardCharts::BARRAS_LARGURA - 12 }}" y1="{{ App\Support\DashboardCharts::BARRAS_MEIO }}" y2="{{ App\Support\DashboardCharts::BARRAS_MEIO }}" class="eixo-meio"/>
        {{-- A parte de estorno é um trecho âmbar na ponta da própria barra,
             recortado pela silhueta dela para manter a ponta arredondada. --}}
        @php($meio = App\Support\DashboardCharts::BARRAS_MEIO)
        @foreach ($graficos->barras as $barra)
            @if ($barra['alturaEntrou'] > 0)
                <rect id="be{{ $loop->index }}" x="{{ $barra['x'] - 4 }}" y="{{ $meio - 4 - $barra['alturaEntrou'] }}" width="8" height="{{ $barra['alturaEntrou'] }}" rx="4" class="b-entrada"><title>entrou {{ $barra['entrou'] }}</title></rect>
                @if ($barra['alturaEntrouEstorno'] > 0)
                    <clipPath id="ce{{ $loop->index }}"><use href="#be{{ $loop->index }}"/></clipPath>
                    <rect x="{{ $barra['x'] - 4 }}" y="{{ $meio - 4 - $barra['alturaEntrou'] }}" width="8" height="{{ $barra['alturaEntrouEstorno'] }}" clip-path="url(#ce{{ $loop->index }})" class="b-estorno"><title>entrou {{ $barra['entrou'] }}</title></rect>
                @endif
            @endif
            @if ($barra['alturaSaiu'] > 0)
                <rect id="bs{{ $loop->index }}" x="{{ $barra['x'] - 4 }}" y="{{ $meio + 4 }}" width="8" height="{{ $barra['alturaSaiu'] }}" rx="4" class="b-saida"><title>saiu {{ $barra['saiu'] }}</title></rect>
                @if ($barra['alturaSaiuEstorno'] > 0)
                    <clipPath id="cs{{ $loop->index }}"><use href="#bs{{ $loop->index }}"/></clipPath>
                    <rect x="{{ $barra['x'] - 4 }}" y="{{ $meio + 4 + $barra['alturaSaiu'] - $barra['alturaSaiuEstorno'] }}" width="8" height="{{ $barra['alturaSaiuEstorno'] }}" clip-path="url(#cs{{ $loop->index }})" class="b-estorno"><title>saiu {{ $barra['saiu'] }}</title></rect>
                @endif
            @endif
            @if ($barra['rotulo'])
                <text x="{{ $barra['x'] }}" y="{{ $graficos->barrasAltura - 8 }}" class="eixo rotulo-barra" text-anchor="middle">{{ $barra['rotulo'] }}</text>
            @endif
        @endforeach
    </svg>
    <ul class="legenda"><li>entrou</li><li class="saida">saiu</li>@if ($graficos->temEstorno)<li class="estorno">estornado</li>@endif</ul>
</section>
