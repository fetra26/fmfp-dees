@php
    use Filament\Support\Enums\IconPosition;
    use Filament\Widgets\View\Components\StatsOverviewWidgetComponent\StatComponent\DescriptionComponent;
    use Filament\Widgets\View\Components\StatsOverviewWidgetComponent\StatComponent\StatsOverviewWidgetStatChartComponent;
    use Illuminate\View\ComponentAttributeBag;

    $chartColor = $getChartColor() ?? 'gray';
    $descriptionColor = $getDescriptionColor() ?? 'gray';
    $descriptionIcon = $getDescriptionIcon();
    $descriptionIconPosition = $getDescriptionIconPosition();
    $url = $getUrl();
    $tag = $url ? 'a' : 'div';
    $chartDataChecksum = $generateChartDataChecksum();

    // ── Icône du carré, à droite de la carte ────────────────────────────
    // La plupart des compteurs de l'application ne définissent que
    // descriptionIcon(), pas icon(). Plutôt que de reprendre chaque widget,
    // on retombe sur le second : le carré apparaît alors partout, y compris
    // sur les compteurs à venir. Quand l'icône vient de là, on ne la répète
    // pas dans la description, sans quoi elle s'afficherait deux fois.
    $iconeCarre = $getIcon() ?? $descriptionIcon;
    $iconePromue = $getIcon() === null && $descriptionIcon !== null;

    // Teinte du carré, reprise de la couleur du compteur. Les classes sont
    // écrites en toutes lettres pour que Tailwind les détecte au build.
    $fondIcone = match ($descriptionColor) {
        'success' => 'bg-emerald-500',
        'danger'  => 'bg-red-500',
        'warning' => 'bg-amber-500',
        'info'    => 'bg-sky-500',
        'gray'    => 'bg-slate-500',
        default   => 'bg-blue-600',
    };
@endphp

<{!! $tag !!}
    @if ($url)
        {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab()) }}
    @endif
    {{
        $getExtraAttributeBag()
            ->class([
                'fi-wi-stats-overview-stat',
            ])
    }}
>
    {{-- Disposition de la maquette Vision UI : bloc de texte à gauche,
         icône dans un carré arrondi à droite, alignés verticalement. --}}
    <div class="flex items-center justify-between gap-4">
        <div class="fi-wi-stats-overview-stat-content min-w-0">
            <div class="fi-wi-stats-overview-stat-label-ctn">
                <span class="fi-wi-stats-overview-stat-label">
                    {{ $getLabel() }}
                </span>
            </div>

            <div class="fi-wi-stats-overview-stat-value">
                {{ $getValue() }}
            </div>

            @if ($description = $getDescription())
                <div
                    {{ (new ComponentAttributeBag)->color(DescriptionComponent::class, $descriptionColor)->class(['fi-wi-stats-overview-stat-description']) }}
                >
                    @if (! $iconePromue && $descriptionIcon && in_array($descriptionIconPosition, [IconPosition::Before, 'before']))
                        {{ \Filament\Support\generate_icon_html($descriptionIcon, attributes: (new ComponentAttributeBag)) }}
                    @endif

                    <span>
                        {{ $description }}
                    </span>

                    @if (! $iconePromue && $descriptionIcon && in_array($descriptionIconPosition, [IconPosition::After, 'after']))
                        {{ \Filament\Support\generate_icon_html($descriptionIcon, attributes: (new ComponentAttributeBag)) }}
                    @endif
                </div>
            @endif
        </div>

        @if ($iconeCarre)
            <div
                @class([
                    'shrink-0 flex items-center justify-center rounded-xl size-11 shadow-sm',
                    $fondIcone,
                ])
            >
                {{ \Filament\Support\generate_icon_html($iconeCarre, attributes: (new ComponentAttributeBag)->class(['size-6 text-white'])) }}
            </div>
        @endif
    </div>

    @if ($chart = $getChart())
        {{-- Fonction vide pour initialiser le composant Alpine en attendant
             son chargement par x-load : évite x-ignore, et laisse le graphique
             se mettre à jour via le polling Livewire. --}}
        <div x-data="{ statsOverviewStatChart() {} }">
            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('stats-overview/stat/chart', 'filament/widgets') }}"
                x-data="statsOverviewStatChart({
                            dataChecksum: @js($chartDataChecksum),
                            labels: @js(array_keys($chart)),
                            values: @js(array_values($chart)),
                        })"
                {{ (new ComponentAttributeBag)->color(StatsOverviewWidgetStatChartComponent::class, $chartColor)->class(['fi-wi-stats-overview-stat-chart']) }}
            >
                <canvas x-ref="canvas"></canvas>

                <span
                    x-ref="backgroundColorElement"
                    class="fi-wi-stats-overview-stat-chart-bg-color"
                ></span>

                <span
                    x-ref="borderColorElement"
                    class="fi-wi-stats-overview-stat-chart-border-color"
                ></span>
            </div>
        </div>
    @endif
</{!! $tag !!}>
