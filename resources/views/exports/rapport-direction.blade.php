<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Direction — SEER (FMFP-DEES)</title>
    <style>
        @page { margin: 1.5cm 1.2cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { color: #1e40af; font-size: 22px; margin: 0 0 4px; }
        h2 { color: #1e40af; font-size: 14px; margin-top: 18px; border-bottom: 2px solid #1e40af; padding-bottom: 4px; }
        h3 { color: #374151; font-size: 12px; margin-top: 12px; margin-bottom: 4px; }
        .header { border-bottom: 3px solid #1e40af; padding-bottom: 10px; margin-bottom: 15px; }
        .header .subtitle { color: #6b7280; font-size: 11px; }
        .header-flex { width: 100%; }
        .header-flex td { border: none; padding: 0; vertical-align: middle; }
        .header-logo { width: 60px; }
        .header-brand { font-size: 26px; font-weight: 900; color: #1e40af; letter-spacing: 0.05em; margin: 0; }
        .header-acronym { font-size: 9px; color: #6b7280; letter-spacing: 0.15em; text-transform: uppercase; font-weight: 700; margin-top: 2px; }
        .kpi-grid { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 8px; }
        .kpi-grid td { padding: 0; border: none; vertical-align: top; }
        .kpi { padding: 10px 12px; background: #f3f4f6; border-left: 4px solid #3b82f6; }
        .kpi .label { font-size: 10px; color: #6b7280; text-transform: uppercase; font-weight: 600; }
        .kpi .value { font-size: 16px; font-weight: bold; color: #1e40af; margin-top: 3px; }
        .kpi .description { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .kpi-danger { border-left-color: #ef4444; }
        .kpi-warning { border-left-color: #f59e0b; }
        .kpi-success { border-left-color: #10b981; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #1e40af; color: white; padding: 6px; text-align: left; font-size: 10px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .badge-rouge { background: #fee2e2; color: #991b1b; }
        .badge-orange { background: #fed7aa; color: #9a3412; }
        .badge-vert { background: #d1fae5; color: #065f46; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #6b7280; text-align: center; }
        .small { font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>

<div class="header">
    <table class="header-flex">
        <tr>
            <td class="header-logo">
                {{-- Logo SEER en PNG (compat DomPDF 100%) --}}
                <img src="{{ public_path('images/logo-seer.png') }}" width="52" height="52" alt="SEER">
            </td>
            <td>
                <div class="header-brand">SEER</div>
                <div class="header-acronym">Suivi · Évaluation · Études · Rapports</div>
            </td>
            <td style="text-align: right; font-size: 10px; color: #6b7280;">
                <b>FMFP</b><br>
                Direction des Études<br>
                et du Suivi-Évaluation
            </td>
        </tr>
    </table>
    <h1 style="margin-top: 12px;">Rapport Direction</h1>
    <div class="subtitle">
        Synthèse stratégique — Généré le {{ $stats['date_generation'] }}
    </div>
</div>

{{-- ═══════════════ INDICATEURS GÉNÉRAUX ═══════════════ --}}
<h2>Indicateurs généraux</h2>
<table class="kpi-grid">
    <tr>
        <td style="width: 33.33%;">
            <div class="kpi">
                <div class="label">Projets actifs</div>
                <div class="value">{{ $stats['total_projets'] }}</div>
                <div class="description">Au total dans la base</div>
            </div>
        </td>
        <td style="width: 33.33%;">
            <div class="kpi">
                <div class="label">Porteurs</div>
                <div class="value">{{ $stats['total_porteurs'] }}</div>
                <div class="description">Entreprises bénéficiaires</div>
            </div>
        </td>
        <td style="width: 33.33%;">
            <div class="kpi kpi-success">
                <div class="label">Taux consommation</div>
                <div class="value">{{ $tauxConsommation }}%</div>
                <div class="description">Engagé vs Versé</div>
            </div>
        </td>
    </tr>
</table>

{{-- ═══════════════ BILAN FINANCIER ═══════════════ --}}
<h2>Bilan financier</h2>
<table>
    <thead>
        <tr>
            <th>Poste</th>
            <th class="text-right">Montant (Ar)</th>
            <th class="text-right">%</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Allocation totale engagée</strong></td>
            <td class="text-right"><strong>{{ number_format($allocationTotale, 0, ',', ' ') }}</strong></td>
            <td class="text-right">100 %</td>
        </tr>
        <tr>
            <td>Tranche J1 (versée)</td>
            <td class="text-right">{{ number_format($paiementsJ1, 0, ',', ' ') }}</td>
            <td class="text-right">{{ $allocationTotale > 0 ? round($paiementsJ1 / $allocationTotale * 100, 1) : 0 }} %</td>
        </tr>
        <tr>
            <td>Tranche J2 (versée)</td>
            <td class="text-right">{{ number_format($paiementsJ2, 0, ',', ' ') }}</td>
            <td class="text-right">{{ $allocationTotale > 0 ? round($paiementsJ2 / $allocationTotale * 100, 1) : 0 }} %</td>
        </tr>
        <tr>
            <td>Tranche J3 (versée)</td>
            <td class="text-right">{{ number_format($paiementsJ3, 0, ',', ' ') }}</td>
            <td class="text-right">{{ $allocationTotale > 0 ? round($paiementsJ3 / $allocationTotale * 100, 1) : 0 }} %</td>
        </tr>
        <tr style="background: #eff6ff;">
            <td><strong>Total versé</strong></td>
            <td class="text-right"><strong>{{ number_format($totalVerse, 0, ',', ' ') }}</strong></td>
            <td class="text-right"><strong>{{ $tauxConsommation }} %</strong></td>
        </tr>
    </tbody>
</table>

{{-- ═══════════════ ALERTES ═══════════════ --}}
<h2>Suivi des alertes</h2>
<table class="kpi-grid">
    <tr>
        <td style="width: 33.33%;">
            <div class="kpi kpi-danger">
                <div class="label"><span style="display:inline-block;width:10px;height:10px;background:#dc2626;border-radius:50%;vertical-align:middle;margin-right:5px;"></span>Alertes rouges</div>
                <div class="value" style="color: #dc2626;">{{ $alertesRouges }}</div>
                <div class="description">Procédure de résiliation (90 j+)</div>
            </div>
        </td>
        <td style="width: 33.33%;">
            <div class="kpi kpi-warning">
                <div class="label"><span style="display:inline-block;width:10px;height:10px;background:#f59e0b;border-radius:50%;vertical-align:middle;margin-right:5px;"></span>Alertes oranges</div>
                <div class="value" style="color: #b45309;">{{ $alertesOranges }}</div>
                <div class="description">Mise en demeure (60-89 j)</div>
            </div>
        </td>
        <td style="width: 33.33%;">
            <div class="kpi kpi-success">
                <div class="label"><span style="display:inline-block;width:10px;height:10px;background:#10b981;border-radius:50%;vertical-align:middle;margin-right:5px;"></span>Alertes vertes</div>
                <div class="value" style="color: #059669;">{{ $alertesVertes }}</div>
                <div class="description">Relance préventive (30-59 j)</div>
            </div>
        </td>
    </tr>
</table>

{{-- ═══════════════ BÉNÉFICIAIRES ═══════════════ --}}
<h2>👥 Performance bénéficiaires (Prévu vs Réalisé)</h2>
<table>
    <thead>
        <tr>
            <th>Catégorie</th>
            <th class="text-right">Prévu</th>
            <th class="text-right">Réalisé</th>
            <th class="text-right">Taux atteinte</th>
        </tr>
    </thead>
    <tbody>
        @php
            $cats = [
                ['Total bénéficiaires', $prevu->total ?? 0, $real->total ?? 0],
                ['Hommes', $prevu->h ?? 0, $real->h ?? 0],
                ['Femmes', $prevu->f ?? 0, $real->f ?? 0],
                ['Jeunes', $prevu->jeunes ?? 0, $real->jeunes ?? 0],
                ['FPE (formation pré-emploi)', $prevu->fpe ?? 0, $real->fpe ?? 0],
                ['Femmes cadres', $prevu->cadres ?? 0, $real->cadres ?? 0],
            ];
        @endphp
        @foreach ($cats as $cat)
            <tr>
                <td>{{ $cat[0] }}</td>
                <td class="text-right">{{ number_format($cat[1], 0, ',', ' ') }}</td>
                <td class="text-right">{{ number_format($cat[2], 0, ',', ' ') }}</td>
                <td class="text-right">
                    {{ $cat[1] > 0 ? round($cat[2] / $cat[1] * 100, 1) . ' %' : '—' }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ═══════════════ RÉPARTITION RÉGIONALE ═══════════════ --}}
<h2>🗺️ Top 10 — Répartition par région</h2>
<table>
    <thead>
        <tr>
            <th>Région</th>
            <th class="text-right">Nb projets</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($parRegion as $r)
            <tr>
                <td>{{ $r->region }}</td>
                <td class="text-right">{{ $r->total }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ═══════════════ RÉPARTITION SECTORIELLE ═══════════════ --}}
<h2>🏭 Top 10 — Répartition par secteur</h2>
<table>
    <thead>
        <tr>
            <th>Secteur</th>
            <th class="text-right">Nb projets</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($parSecteur as $s)
            <tr>
                <td>{{ $s->secteur }}</td>
                <td class="text-right">{{ $s->total }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ═══════════════ FINANCEMENT PAR GUICHET ═══════════════ --}}
<h2>💼 Financement par guichet</h2>
<table>
    <thead>
        <tr>
            <th>Guichet</th>
            <th class="text-right">Nb projets</th>
            <th class="text-right">Montant total (Ar)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($financementGuichet as $g)
            <tr>
                <td>{{ $g->guichet }}</td>
                <td class="text-right">{{ $g->nb }}</td>
                <td class="text-right">{{ number_format($g->total, 0, ',', ' ') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    Fonds Malgache de Formation Professionnelle — Direction des Études et du Suivi-Évaluation
    <br>
    Document généré automatiquement par la plateforme SEER (FMFP-DEES)
</div>

</body>
</html>
