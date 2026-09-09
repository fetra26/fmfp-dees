@php
    $porteurProjUrl = \App\Filament\Resources\PorteurProjs\PorteurProjResource::getUrl('create');
    $projetEditUrl = fn ($id) => \App\Filament\Resources\Projets\ProjetResource::getUrl('edit', ['record' => $id]);
@endphp

<style>
    .seer-orph-container { display: flex; flex-direction: column; gap: 16px; }
    .seer-orph-alert {
        padding: 12px 16px;
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        border-left: 4px solid #f59e0b;
        border-radius: 8px;
        color: #78350f;
        font-size: 0.875rem;
        line-height: 1.5;
    }
    .dark .seer-orph-alert {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.08));
        color: #fbbf24;
        border-left-color: #f59e0b;
    }
    .seer-orph-empty {
        padding: 32px;
        text-align: center;
        color: rgb(107, 114, 128);
        background: rgb(249, 250, 251);
        border-radius: 8px;
        font-size: 0.9rem;
    }
    .seer-orph-table-wrap {
        border: 1px solid rgb(229, 231, 235);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .dark .seer-orph-table-wrap { border-color: rgb(55, 65, 81); }
    .seer-orph-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }
    .seer-orph-table thead {
        background: linear-gradient(180deg, #f3f4f6, #e5e7eb);
    }
    .dark .seer-orph-table thead {
        background: linear-gradient(180deg, rgb(31, 41, 55), rgb(17, 24, 39));
    }
    .seer-orph-table th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: rgb(75, 85, 99);
        border-bottom: 2px solid rgb(209, 213, 219);
    }
    .dark .seer-orph-table th {
        color: rgb(156, 163, 175);
        border-bottom-color: rgb(55, 65, 81);
    }
    .seer-orph-table td {
        padding: 12px 14px;
        border-bottom: 1px solid rgb(243, 244, 246);
        vertical-align: middle;
    }
    .dark .seer-orph-table td {
        border-bottom-color: rgb(55, 65, 81);
        color: rgb(229, 231, 235);
    }
    .seer-orph-table tr:last-child td { border-bottom: 0; }
    .seer-orph-table tr:hover td { background: rgb(249, 250, 251); }
    .dark .seer-orph-table tr:hover td { background: rgb(31, 41, 55); }
    .seer-orph-ref {
        font-family: 'Consolas', 'Monaco', monospace;
        font-weight: 700;
        color: #1e40af;
        background: #eff6ff;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
    }
    .dark .seer-orph-ref {
        background: rgba(30, 64, 175, 0.15);
        color: #93c5fd;
    }
    .seer-orph-intitule { color: rgb(31, 41, 55); }
    .dark .seer-orph-intitule { color: rgb(229, 231, 235); }
    .seer-orph-date { font-size: 0.75rem; color: rgb(107, 114, 128); font-style: italic; }
    .seer-orph-actions {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    .seer-orph-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.15s;
        white-space: nowrap;
    }
    .seer-orph-btn-edit {
        background: #2563eb;
        color: white;
    }
    .seer-orph-btn-edit:hover { background: #1d4ed8; }
    .seer-orph-btn-add {
        background: #059669;
        color: white;
    }
    .seer-orph-btn-add:hover { background: #047857; }
    .seer-orph-note {
        margin-top: 8px;
        font-size: 0.75rem;
        color: rgb(107, 114, 128);
        font-style: italic;
        text-align: center;
    }
</style>

<div class="seer-orph-container">
    @if($projets->isEmpty())
        <div class="seer-orph-empty">
            ✅ Aucun projet orphelin — tout est cohérent.
        </div>
    @else
        <div class="seer-orph-alert">
            <strong>{{ $projets->count() }} projet(s)</strong> sans porteur associé.
            Chaque projet doit avoir au moins <strong>1 porteur</strong> et <strong>1 convention</strong>
            pour apparaître dans les statistiques.
        </div>

        <div class="seer-orph-table-wrap">
            <table class="seer-orph-table">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Intitulé</th>
                        <th>Créé</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($projets as $p)
                        <tr>
                            <td>
                                <span class="seer-orph-ref">{{ $p->reference }}</span>
                            </td>
                            <td class="seer-orph-intitule">
                                {{ \Illuminate\Support\Str::limit($p->intitule ?: '—', 60) }}
                            </td>
                            <td class="seer-orph-date">
                                {{ $p->created_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td>
                                <div class="seer-orph-actions">
                                    <a href="{{ $projetEditUrl($p->id) }}" class="seer-orph-btn seer-orph-btn-edit">
                                        ✎ Éditer
                                    </a>
                                    <a href="{{ $porteurProjUrl }}?projet_id={{ $p->id }}" class="seer-orph-btn seer-orph-btn-add">
                                        + Ajouter porteur
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($projets->count() >= 50)
            <div class="seer-orph-note">
                Affichage limité aux 50 plus récents.
            </div>
        @endif
    @endif
</div>
