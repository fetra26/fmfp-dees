<x-filament-panels::page>
    {{-- Rappel de la règle métier : sans elle, les niveaux de couleur ne
         veulent rien dire pour quelqu'un qui arrive sur l'écran. --}}
    <div class="fi-section rounded-xl p-4 text-sm">
        <div class="font-semibold mb-2">Seuils de relance</div>
        <ul class="space-y-1">
            <li><span class="font-semibold text-success-600">Verte</span> — 30 à 59 jours de dépassement : première relance préventive.</li>
            <li><span class="font-semibold text-warning-600">Orange</span> — 60 à 89 jours : deuxième relance et lettre de mise en demeure.</li>
            <li><span class="font-semibold text-danger-600">Rouge</span> — 90 jours et plus : procédure de résiliation.</li>
        </ul>
        <div class="mt-2 text-xs opacity-75">
            Les niveaux sont recalculés chaque nuit. « Verte » couvre aussi les projets
            encore dans les temps : le filtre « Échéance dépassée » les écarte par défaut.
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
