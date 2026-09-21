@php
    $comptes = $this->comptesParNiveau();

    // Le style de chaque onglet, actif ou non. Les classes sont écrites en
    // toutes lettres pour que Tailwind les détecte au build : une classe
    // construite dynamiquement serait purgée et l'onglet resterait incolore.
    $styles = [
        'tous'   => ['actif' => 'bg-primary-600 text-white border-primary-600',
                     'repos' => 'border-gray-300 dark:border-gray-600 hover:border-primary-400'],
        'rouge'  => ['actif' => 'bg-danger-600 text-white border-danger-600',
                     'repos' => 'border-gray-300 dark:border-gray-600 hover:border-danger-400'],
        'orange' => ['actif' => 'bg-warning-500 text-white border-warning-500',
                     'repos' => 'border-gray-300 dark:border-gray-600 hover:border-warning-400'],
        'verte'  => ['actif' => 'bg-success-600 text-white border-success-600',
                     'repos' => 'border-gray-300 dark:border-gray-600 hover:border-success-400'],
    ];

    // Couleur de l'icône quand l'onglet est au repos. Sur fond coloré (onglet
    // actif), elle passe en blanc pour rester lisible.
    $teintes = [
        'tous'   => 'text-gray-500 dark:text-gray-400',
        'rouge'  => 'text-danger-500',
        'orange' => 'text-warning-500',
        'verte'  => 'text-success-500',
    ];

    $icones = ['tous' => 'heroicon-o-queue-list']
        + collect(\App\Filament\Pages\ProjetsEnAlerte::NIVEAUX)->map(fn ($n) => $n[2])->all();

    $onglets = ['tous' => ['Toutes', 'Tous niveaux confondus']]
        + collect(\App\Filament\Pages\ProjetsEnAlerte::NIVEAUX)
            ->map(fn ($n) => [$n[0], $n[3]])
            ->all();
@endphp

<x-filament-panels::page>
    {{-- Onglets par niveau : la couleur porte l'urgence, le nombre dit
         l'ampleur. Comptés sur les seules échéances dépassées. --}}
    <div class="flex flex-wrap gap-2">
        @foreach ($onglets as $cle => [$libelle, $description])
            @php
                $estActif = ($cle === 'tous' && $niveau === null) || $niveau === $cle;
                $style = $estActif ? $styles[$cle]['actif'] : $styles[$cle]['repos'];
            @endphp

            <button
                type="button"
                wire:click="changerNiveau({{ $cle === 'tous' ? 'null' : "'{$cle}'" }})"
                title="{{ $description }}"
                @class([
                    'flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold transition',
                    $style,
                ])
            >
                <x-filament::icon
                    :icon="$icones[$cle]"
                    @class(['size-5', $estActif ? 'text-white' : $teintes[$cle]])
                />

                <span>{{ $libelle }}</span>

                <span @class([
                    'rounded-md px-1.5 py-0.5 text-xs font-bold',
                    'bg-white/25' => $estActif,
                    'bg-gray-100 dark:bg-gray-700' => ! $estActif,
                ])>{{ number_format($comptes[$cle] ?? 0, 0, ',', ' ') }}</span>
            </button>
        @endforeach
    </div>

    {{-- Rappel de la règle : sans elle, les couleurs ne veulent rien dire
         pour quelqu'un qui arrive sur l'écran. --}}
    <div class="fi-section rounded-xl p-4 text-sm">
        <div class="font-semibold mb-2">Seuils de relance</div>
        <ul class="space-y-1">
            <li><span class="font-semibold text-success-600">Verte</span> — 30 à 59 jours de dépassement : première relance préventive.</li>
            <li><span class="font-semibold text-warning-600">Orange</span> — 60 à 89 jours : deuxième relance et lettre de mise en demeure.</li>
            <li><span class="font-semibold text-danger-600">Rouge</span> — 90 jours et plus : procédure de résiliation.</li>
        </ul>
        <div class="mt-2 text-xs opacity-75">
            Un projet quitte ce suivi dès qu'il est <strong>clôturé</strong>, annulé, résilié,
            ou que ses tranches J1 et J2 sont versées — il n'y a alors plus rien à relancer.
            Les niveaux sont recalculés chaque nuit.
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
