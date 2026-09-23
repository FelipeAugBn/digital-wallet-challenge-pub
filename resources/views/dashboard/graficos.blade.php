{{-- Os três gráficos do painel. Todo número desenhado também está em texto:
     título e descrição de cada SVG, rótulos de eixo e a legenda do anel. --}}
<section class="graficos" aria-label="Gráficos da carteira">
    <div class="cartao largo">
        <h2>Saldo nas últimas cinco semanas</h2>
        <p class="sub">Um ponto por dia com movimento.@if ($graficos->temEstorno) O ponto âmbar marca um estorno.@endif</p>

        <svg viewBox="0 0 {{ App\Support\DashboardCharts::CURVA_LARGURA }} {{ App\Support\DashboardCharts::CURVA_ALTURA }}" class="curva" role="img" aria-labelledby="curva-titulo curva-desc">
            <title id="curva-titulo">Saldo nas últimas cinco semanas</title>
            <desc id="curva-desc">{{ $graficos->descricaoCurva }}</desc>
            <defs>
                <linearGradient id="curva-area" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#0f6b5c" stop-opacity=".26"/>
                    <stop offset="1" stop-color="#0f6b5c" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <path d="{{ $graficos->area }}" fill="url(#curva-area)"/>
            <path d="{{ $graficos->linha }}" class="traco"/>
            @foreach ($graficos->pontos as $ponto)
                <circle cx="{{ $ponto['x'] }}" cy="{{ $ponto['y'] }}" r="3.5" class="{{ $ponto['estorno'] ? 'marca-estorno' : 'marca-dia' }}"><title>{{ $ponto['titulo'] }}</title></circle>
            @endforeach
            <circle cx="{{ $graficos->fim['x'] }}" cy="{{ $graficos->fim['y'] }}" r="9" class="halo"/>
            <circle cx="{{ $graficos->fim['x'] }}" cy="{{ $graficos->fim['y'] }}" r="4.5" class="ponto-fim"/>
            <text x="{{ $graficos->fim['x'] + 14 }}" y="{{ $graficos->fim['y'] + 5 }}" class="rotulo-fim">{{ $graficos->saldoAtual }}</text>
            @foreach ($graficos->eixo as $marca)
                <text x="{{ $marca['x'] }}" y="{{ App\Support\DashboardCharts::CURVA_ALTURA - 8 }}" class="eixo" text-anchor="{{ $marca['ancora'] }}">{{ $marca['rotulo'] }}</text>
            @endforeach
        </svg>
    </div>

    <div class="cartao">
        <h2>Entradas e saídas</h2>
        <p class="sub">Por dia com movimento, nas últimas cinco semanas</p>

        <svg viewBox="0 0 {{ App\Support\DashboardCharts::BARRAS_LARGURA }} {{ $graficos->barrasAltura }}" class="barras" role="img" aria-labelledby="barras-titulo barras-desc">
            <title id="barras-titulo">Entradas e saídas por dia</title>
            <desc id="barras-desc">{{ $graficos->descricaoBarras }}</desc>
            <line x1="12" x2="{{ App\Support\DashboardCharts::BARRAS_LARGURA - 12 }}" y1="96" y2="96" class="eixo-meio"/>
            {{-- A parte de estorno é um trecho ocre na ponta da própria barra,
                 recortado pela silhueta dela para manter a ponta arredondada. --}}
            @foreach ($graficos->barras as $barra)
                @if ($barra['alturaEntrou'] > 0)
                    <rect id="be{{ $loop->index }}" x="{{ $barra['x'] - 4 }}" y="{{ 91 - $barra['alturaEntrou'] }}" width="8" height="{{ $barra['alturaEntrou'] }}" rx="4" class="b-entrada"><title>entrou {{ $barra['entrou'] }}</title></rect>
                    @if ($barra['alturaEntrouEstorno'] > 0)
                        <clipPath id="ce{{ $loop->index }}"><use href="#be{{ $loop->index }}"/></clipPath>
                        <rect x="{{ $barra['x'] - 4 }}" y="{{ 91 - $barra['alturaEntrou'] }}" width="8" height="{{ $barra['alturaEntrouEstorno'] }}" clip-path="url(#ce{{ $loop->index }})" class="b-estorno"><title>entrou {{ $barra['entrou'] }}</title></rect>
                    @endif
                @endif
                @if ($barra['alturaSaiu'] > 0)
                    <rect id="bs{{ $loop->index }}" x="{{ $barra['x'] - 4 }}" y="101" width="8" height="{{ $barra['alturaSaiu'] }}" rx="4" class="b-saida"><title>saiu {{ $barra['saiu'] }}</title></rect>
                    @if ($barra['alturaSaiuEstorno'] > 0)
                        <clipPath id="cs{{ $loop->index }}"><use href="#bs{{ $loop->index }}"/></clipPath>
                        <rect x="{{ $barra['x'] - 4 }}" y="{{ 101 + $barra['alturaSaiu'] - $barra['alturaSaiuEstorno'] }}" width="8" height="{{ $barra['alturaSaiuEstorno'] }}" clip-path="url(#cs{{ $loop->index }})" class="b-estorno"><title>saiu {{ $barra['saiu'] }}</title></rect>
                    @endif
                @endif
                @if ($barra['rotulo'])
                    <text x="{{ $barra['x'] }}" y="{{ $graficos->barrasAltura - 8 }}" class="eixo rotulo-barra" text-anchor="middle">{{ $barra['rotulo'] }}</text>
                @endif
            @endforeach
        </svg>
        <ul class="legenda"><li>entrou</li><li class="saida">saiu</li>@if ($graficos->temEstorno)<li class="estorno">estornado</li>@endif</ul>
    </div>

    <div class="cartao">
        <h2>{{ $graficos->mes }}</h2>
        <p class="sub">Quanto entrou e quanto saiu no mês</p>

        <div class="anel-caixa">
            <svg viewBox="0 0 140 140" class="anel" role="img" aria-labelledby="anel-titulo anel-desc">
                <title id="anel-titulo">{{ $graficos->mes }}: o que entrou e o que saiu</title>
                <desc id="anel-desc">{{ $graficos->descricaoAnel }}</desc>
                <g transform="rotate(-90 70 70)">
                    <circle r="{{ App\Support\DashboardCharts::ANEL_RAIO }}" cx="70" cy="70" class="trilho"/>
                    @unless ($graficos->mesVazio)
                        <circle r="{{ App\Support\DashboardCharts::ANEL_RAIO }}" cx="70" cy="70" class="seg-entrou" stroke-dasharray="{{ $graficos->arcoEntrou }} 1000" stroke-dashoffset="-3"/>
                        <circle r="{{ App\Support\DashboardCharts::ANEL_RAIO }}" cx="70" cy="70" class="seg-saiu" stroke-dasharray="{{ $graficos->arcoSaiu }} 1000" stroke-dashoffset="{{ $graficos->deslocamentoSaiu }}"/>
                    @endunless
                </g>
                @if ($graficos->mesVazio)
                    <text x="70" y="74" class="anel-percentual" text-anchor="middle">—</text>
                    <text x="70" y="90" class="anel-lado" text-anchor="middle">sem movimento</text>
                @else
                    <text x="70" y="74" class="anel-percentual" text-anchor="middle">{{ $graficos->percentual }}%</text>
                    <text x="70" y="90" class="anel-lado" text-anchor="middle">{{ $graficos->lado }}</text>
                @endif
            </svg>

            <ul class="anel-legenda">
                @foreach ($graficos->legendaAnel as $linha)
                    <li class="{{ $linha['classe'] }}"><span>{{ $linha['rotulo'] }}</span><b>{{ $linha['valor'] }}</b></li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
