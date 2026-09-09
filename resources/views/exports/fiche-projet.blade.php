<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SEER — Fiche projet {{ $porteurProj->projet?->reference }}</title>
    <style>
        @page {
            margin: 2.2cm 1.5cm 2cm 1.5cm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.4;
        }

        /* ═══ HEADER répété sur chaque page ═══ */
        header {
            position: fixed;
            top: -1.5cm;
            left: 0;
            right: 0;
            height: 1.3cm;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 6px;
        }
        header table { width: 100%; border-collapse: collapse; }
        header td { padding: 0; vertical-align: middle; border: none; }
        header .brand {
            font-size: 15px;
            font-weight: 900;
            color: #1e40af;
            letter-spacing: 0.06em;
        }
        header .sub {
            font-size: 8px;
            color: #6b7280;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
        }
        header .ref {
            text-align: right;
            font-size: 9px;
            color: #374151;
        }
        header .ref b { color: #1e40af; }

        /* ═══ FOOTER répété sur chaque page ═══ */
        footer {
            position: fixed;
            bottom: -1.3cm;
            left: 0;
            right: 0;
            height: 1cm;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 8px;
            color: #6b7280;
        }
        footer table { width: 100%; border-collapse: collapse; }
        footer td { border: none; padding: 0; }
        .page-num:after { content: "Page " counter(page) " sur " counter(pages); }

        /* ═══ COUVERTURE ═══ */
        .cover {
            background: #1e40af;
            color: white;
            padding: 30px 25px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .cover .kicker {
            font-size: 10px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            opacity: 0.85;
            margin-bottom: 8px;
        }
        .cover h1 {
            font-size: 26px;
            font-weight: 900;
            margin: 0 0 6px;
            letter-spacing: -0.01em;
        }
        .cover .porteur {
            font-size: 14px;
            opacity: 0.95;
            margin-bottom: 16px;
        }
        .cover-meta { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .cover-meta td {
            border: none;
            padding: 4px 8px;
            font-size: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
        }
        .cover-meta .label {
            font-size: 8px;
            opacity: 0.75;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .cover-meta .value {
            font-size: 11px;
            font-weight: 700;
        }

        /* ═══ SECTIONS ═══ */
        .section {
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .section-title {
            background: #f3f4f6;
            border-left: 4px solid #1e40af;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 900;
            color: #1e40af;
            margin-bottom: 8px;
            letter-spacing: 0.01em;
        }
        .section-1 { border-left-color: #1e40af; }
        .section-1 .section-title { color: #1e40af; }
        .section-2 { border-left-color: #10b981; }
        .section-2 .section-title { color: #10b981; border-left-color: #10b981; }
        .section-3 { border-left-color: #6b7280; }
        .section-3 .section-title { color: #374151; border-left-color: #6b7280; }
        .section-4 { border-left-color: #111827; }
        .section-4 .section-title { color: #111827; border-left-color: #111827; }
        .section-5 { border-left-color: #f59e0b; }
        .section-5 .section-title { color: #b45309; border-left-color: #f59e0b; }
        .section-6 { border-left-color: #fb923c; }
        .section-6 .section-title { color: #c2410c; border-left-color: #fb923c; }
        .section-7 { border-left-color: #7c3aed; }
        .section-7 .section-title { color: #7c3aed; border-left-color: #7c3aed; }
        .section-8 { border-left-color: #92400e; }
        .section-8 .section-title { color: #92400e; border-left-color: #92400e; }

        /* ═══ TABLEAUX ═══ */
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9.5px;
        }
        table.data th {
            background: #f9fafb;
            color: #374151;
            font-weight: 700;
            text-align: left;
            border-bottom: 2px solid #d1d5db;
        }
        table.data tr:nth-child(even) td { background: #fafafa; }
        table.data .label {
            width: 35%;
            color: #6b7280;
            font-weight: 600;
        }
        table.data .value { color: #111827; font-weight: 500; }

        /* ═══ BADGES ═══ */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warn { background: #fef3c7; color: #b45309; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-gray { background: #f3f4f6; color: #4b5563; }

        /* ═══ BAR CHART simple avec HTML/CSS ═══ */
        .bar-chart {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 10px 0;
        }
        .bar-chart .bar-col {
            display: table-cell;
            vertical-align: bottom;
            text-align: center;
        }
        .bar-chart .bar-value {
            font-size: 9px;
            font-weight: 700;
            margin-bottom: 2px;
            color: #111827;
        }
        .bar-chart .bar {
            background: #3b82f6;
            border-radius: 4px 4px 0 0;
            margin: 0 auto;
            width: 30px;
        }
        .bar-chart .bar-label {
            font-size: 8px;
            color: #6b7280;
            margin-top: 4px;
            font-weight: 600;
        }
        .bar-orange { background: #f59e0b !important; }
        .bar-green { background: #10b981 !important; }
        .bar-purple { background: #7c3aed !important; }
        .bar-pink { background: #ec4899 !important; }

        /* ═══ KPI TILES ═══ */
        .kpi-row { display: table; width: 100%; border-spacing: 6px 0; margin-bottom: 8px; }
        .kpi-cell { display: table-cell; }
        .kpi {
            padding: 10px 12px;
            background: #f9fafb;
            border-left: 3px solid #3b82f6;
            border-radius: 4px;
        }
        .kpi .k-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .kpi .k-value {
            font-size: 14px;
            font-weight: 900;
            color: #111827;
            margin-top: 2px;
        }
        .kpi-primary { border-left-color: #1e40af; }
        .kpi-success { border-left-color: #10b981; }
        .kpi-warning { border-left-color: #f59e0b; }
        .kpi-danger { border-left-color: #ef4444; }

        /* ═══ SIGNATURES ═══ */
        .signatures {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .sig-title {
            text-align: center;
            font-size: 11px;
            font-weight: 900;
            color: #1e40af;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            border-top: 2px solid #1e40af;
            border-bottom: 2px solid #1e40af;
            padding: 8px 0;
            margin-bottom: 20px;
        }
        .sig-grid { width: 100%; border-collapse: collapse; }
        .sig-grid td {
            border: none;
            width: 33.33%;
            padding: 12px 8px;
            text-align: center;
            vertical-align: top;
        }
        .sig-role {
            font-size: 9px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .sig-line {
            border-bottom: 1px solid #374151;
            height: 45px;
            margin: 12px 0 6px;
        }
        .sig-date {
            font-size: 8px;
            color: #6b7280;
        }

        /* ═══ WATERMARK ═══ */
        .watermark {
            position: fixed;
            top: 40%;
            left: 25%;
            transform: rotate(-30deg);
            font-size: 90px;
            font-weight: 900;
            color: rgba(239, 68, 68, 0.10);
            letter-spacing: 0.15em;
            z-index: -1;
            pointer-events: none;
        }
        .watermark-brouillon { color: rgba(245, 158, 11, 0.12); }

        /* ═══ CONFIDENTIEL BOX ═══ */
        .confidential-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 8px 12px;
            color: #991b1b;
            font-size: 9px;
            font-style: italic;
            margin: 8px 0;
        }

        /* Utils */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
    </style>
</head>
<body>

{{-- ═══════════ WATERMARK selon statut ═══════════ --}}
@php
    $statutCode = $porteurProj->projet?->statut?->code;
@endphp
@if($statutCode === 'stand_by' || $statutCode === 'attente_pieces_regul')
    <div class="watermark watermark-brouillon">BROUILLON</div>
@elseif($statutCode === 'cloture' || $statutCode === 'annule')
    <div class="watermark">{{ $statutCode === 'annule' ? 'ANNULÉ' : 'VERROUILLÉ' }}</div>
@endif

{{-- ═══════════ HEADER (répété sur chaque page) ═══════════ --}}
<header>
    <table>
        <tr>
            <td style="width: 55px; vertical-align: middle;">
                {{-- Logo SEER en PNG (compat DomPDF 100%) --}}
                <img src="{{ public_path('images/logo-seer.png') }}" width="42" height="42" alt="SEER">
            </td>
            <td>
                <div class="brand">SEER</div>
                <div class="sub">Suivi · Évaluation · Études · Rapports</div>
            </td>
            <td class="ref">
                <div><b>Fiche projet</b></div>
                <div>Réf : <b>{{ $porteurProj->projet?->reference ?: '—' }}</b></div>
                <div>{{ now()->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>
</header>

{{-- ═══════════ FOOTER (répété sur chaque page) ═══════════ --}}
<footer>
    <table>
        <tr>
            <td style="width: 40%;">
                FMFP · Direction des Études et du Suivi-Évaluation<br>
                <em>Document confidentiel — Fonds Malgache de Formation Professionnelle</em>
            </td>
            <td style="width: 30%; text-align: center;">
                <span style="color: #1e40af; font-weight: 700;">SEER</span>
            </td>
            <td style="width: 30%; text-align: right;">
                <span class="page-num"></span>
            </td>
        </tr>
    </table>
</footer>

<main>

{{-- ═══════════ COUVERTURE ═══════════ --}}
<div class="cover">
    <div class="kicker">Fiche projet — SEER</div>
    <h1>{{ $porteurProj->projet?->intitule ?: 'Sans intitulé' }}</h1>
    <div class="porteur">
        {{ $porteurProj->porteur?->raison_sociale ?: '—' }}
    </div>

    <table class="cover-meta">
        <tr>
            <td>
                <div class="label">Référence projet</div>
                <div class="value">{{ $porteurProj->projet?->reference ?: '—' }}</div>
            </td>
            <td>
                <div class="label">Référence convention</div>
                <div class="value">{{ $porteurProj->reference_convention ?: '—' }}</div>
            </td>
            <td>
                <div class="label">Statut</div>
                <div class="value">{{ $porteurProj->projet?->statut?->libelle ?: '—' }}</div>
            </td>
            <td>
                <div class="label">Guichet</div>
                <div class="value">{{ $porteurProj->projet?->guichet?->libelle ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 12px; font-size: 9px; opacity: 0.9;">
        Généré le {{ now()->format('d/m/Y à H:i') }} par <b>{{ $generePar }}</b>
    </div>
</div>

{{-- ═══════════ SECTION 1 — INFORMATION GÉNÉRALE ═══════════ --}}
<div class="section section-1">
    <div class="section-title">1 · INFORMATION GÉNÉRALE</div>

    <table class="data">
        <tr>
            <td class="label">Secteur</td>
            <td class="value">{{ $porteurProj->projet?->secteur?->libelle ?: '—' }}</td>
            <td class="label">Vague</td>
            <td class="value">{{ $porteurProj->projet?->vague?->libelle ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Région</td>
            <td class="value">{{ $porteurProj->projet?->region?->libelle ?: '—' }}</td>
            <td class="label">Date début</td>
            <td class="value">{{ $porteurProj->projet?->date_debut?->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Porteur (entreprise)</td>
            <td class="value" colspan="3"><b>{{ $porteurProj->porteur?->raison_sociale ?: '—' }}</b></td>
        </tr>
        <tr>
            <td class="label">CNaPS porteur</td>
            <td class="value">{{ $porteurProj->porteur?->cnaps ?: '—' }}</td>
            <td class="label">Nb salariés</td>
            <td class="value">{{ $porteurProj->porteur?->nb_salaries ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Responsable</td>
            <td class="value">{{ $porteurProj->porteur?->responsable_nom ?: '—' }}</td>
            <td class="label">Contact</td>
            <td class="value">{{ $porteurProj->porteur?->telephone ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Email</td>
            <td class="value" colspan="3">{{ $porteurProj->porteur?->email ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Adresse</td>
            <td class="value" colspan="3">{{ $porteurProj->porteur?->adresse ?: '—' }}</td>
        </tr>
    </table>

    @if($porteurProj->partenaires->isNotEmpty())
        <div style="margin-top: 10px; font-size: 10px; font-weight: 700; color: #374151;">
            Partenaires ({{ $porteurProj->partenaires->count() }})
        </div>
        <table class="data mt-2">
            <thead>
                <tr>
                    <th>Nom partenaire</th>
                    <th>CNaPS</th>
                    <th class="text-right">Nb salariés</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porteurProj->partenaires as $part)
                    <tr>
                        <td><b>{{ $part->nom }}</b></td>
                        <td>{{ $part->cnaps ?: '—' }}</td>
                        <td class="text-right">{{ $part->nb_salaries ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ═══════════ SECTION 2 — PRÉVISIONNEL ═══════════ --}}
@php $benefPrevu = $porteurProj->benefPrevu; @endphp
@if($benefPrevu)
<div class="section section-2">
    <div class="section-title">2 · PRÉVISIONNEL</div>

    <div class="kpi-row">
        <div class="kpi-cell"><div class="kpi kpi-primary"><div class="k-label">Bénéf total prévu</div><div class="k-value">{{ number_format((int)$benefPrevu->total, 0, ',', ' ') }}</div></div></div>
        <div class="kpi-cell"><div class="kpi kpi-success"><div class="k-label">Hommes</div><div class="k-value">{{ (int)$benefPrevu->h }}</div></div></div>
        <div class="kpi-cell"><div class="kpi kpi-warning"><div class="k-label">Femmes</div><div class="k-value">{{ (int)$benefPrevu->f }}</div></div></div>
        <div class="kpi-cell"><div class="kpi kpi-danger"><div class="k-label">Jeunes</div><div class="k-value">{{ (int)$benefPrevu->jeunes }}</div></div></div>
    </div>

    @php
        $maxVal = max([(int)$benefPrevu->h, (int)$benefPrevu->f, (int)$benefPrevu->jeunes, (int)$benefPrevu->fpe, (int)$benefPrevu->cadres, 1]);
        $barHeight = fn($v) => max(4, round(($v / $maxVal) * 60));
    @endphp
    <div class="bar-chart" style="margin-top: 12px;">
        @foreach([
            ['H',      (int)$benefPrevu->h,      'bar'],
            ['F',      (int)$benefPrevu->f,      'bar-pink'],
            ['Jeunes', (int)$benefPrevu->jeunes, 'bar-orange'],
            ['FPE',    (int)$benefPrevu->fpe,    'bar-green'],
            ['Cadres', (int)$benefPrevu->cadres, 'bar-purple'],
        ] as [$label, $val, $class])
            <div class="bar-col">
                <div class="bar-value">{{ $val }}</div>
                <div class="bar {{ $class }}" style="height: {{ $barHeight($val) }}px;"></div>
                <div class="bar-label">{{ $label }}</div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ═══════════ SECTION 3 — CONTRACTUALISATION ═══════════ --}}
<div class="section section-3">
    <div class="section-title">3 · CONTRACTUALISATION</div>

    <div class="kpi-row">
        <div class="kpi-cell"><div class="kpi kpi-primary"><div class="k-label">Montant total</div><div class="k-value">{{ number_format((float)$porteurProj->montant_total, 0, ',', ' ') }} <span style="font-size:9px">Ar</span></div></div></div>
        <div class="kpi-cell"><div class="kpi kpi-success"><div class="k-label">Financement demandé</div><div class="k-value">{{ number_format((float)$porteurProj->financement_demande, 0, ',', ' ') }} <span style="font-size:9px">Ar</span></div></div></div>
        <div class="kpi-cell"><div class="kpi kpi-warning"><div class="k-label">DT mobilisé</div><div class="k-value">{{ number_format((float)$porteurProj->dt_mobilise, 0, ',', ' ') }} <span style="font-size:9px">Ar</span></div></div></div>
    </div>

    <table class="data mt-2">
        <tr>
            <td class="label">Date notification</td>
            <td class="value">{{ $porteurProj->date_notification?->format('d/m/Y') ?: '—' }}</td>
            <td class="label">Date début conv.</td>
            <td class="value">{{ $porteurProj->date_debut?->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date envoi conv.</td>
            <td class="value">{{ $porteurProj->date_envoi_convention?->format('d/m/Y') ?: '—' }}</td>
            <td class="label">Date fin conv.</td>
            <td class="value">{{ $porteurProj->date_fin?->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date réception conv.</td>
            <td class="value">{{ $porteurProj->date_reception_convention?->format('d/m/Y') ?: '—' }}</td>
            <td class="label">DANO</td>
            <td class="value">{{ $porteurProj->dano_type ?: 'Aucun' }}</td>
        </tr>
    </table>

    @if($porteurProj->appreciation_evaluateur)
        <div style="margin-top: 10px; padding: 8px 12px; background: #f9fafb; border-left: 3px solid #6b7280; font-style: italic; font-size: 9.5px;">
            <b>Appréciation évaluateur :</b> {{ $porteurProj->appreciation_evaluateur }}
        </div>
    @endif
</div>

{{-- ═══════════ SECTION 4 — PAIEMENT (masqué pour non-DAF) ═══════════ --}}
<div class="section section-4">
    <div class="section-title">4 · PAIEMENT</div>

    @if($peutVoirPaiement)
        @php
            $paiements = $porteurProj->paiements->where('is_annule', false);
            $totalVerse = $paiements->sum('montant');
        @endphp

        <div class="kpi-row">
            <div class="kpi-cell"><div class="kpi kpi-primary"><div class="k-label">Allocation consommée</div><div class="k-value">{{ number_format($totalVerse, 0, ',', ' ') }} <span style="font-size:9px">Ar</span></div></div></div>
            <div class="kpi-cell"><div class="kpi kpi-success"><div class="k-label">Nb tranches versées</div><div class="k-value">{{ $paiements->count() }} / 3</div></div></div>
            <div class="kpi-cell"><div class="kpi kpi-warning"><div class="k-label">Situation</div><div class="k-value">{{ ucfirst($porteurProj->situation_alloc ?: '—') }}</div></div></div>
        </div>

        @if($paiements->isNotEmpty())
            <table class="data mt-2">
                <thead>
                    <tr>
                        <th>Tranche</th>
                        <th>Date paiement</th>
                        <th class="text-right">Montant</th>
                        <th>Référence ordre</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paiements as $p)
                        <tr>
                            <td><span class="badge badge-info">{{ $p->ligne }}</span></td>
                            <td>{{ $p->date_paiement?->format('d/m/Y') ?: '—' }}</td>
                            <td class="text-right"><b>{{ number_format($p->montant, 0, ',', ' ') }} Ar</b></td>
                            <td>{{ $p->reference_ordre ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="padding: 8px; text-align: center; color: #6b7280; font-style: italic;">Aucun paiement enregistré.</div>
        @endif
    @else
        <div class="confidential-box">
            <b>[CONFIDENTIEL]</b> Section réservée à l'équipe DAF. Vous n'avez pas les droits pour consulter le détail des paiements.
            <br>
            Total versé (global) : <b>{{ number_format($porteurProj->paiements->where('is_annule', false)->sum('montant'), 0, ',', ' ') }} Ar</b>
        </div>
    @endif
</div>

{{-- ═══════════ SECTION 5 — ALERTE ═══════════ --}}
<div class="section section-5">
    <div class="section-title">5 · ALERTE</div>

    @php
        $niveau = $porteurProj->niveau_alerte ?: 'verte';
        $badgeAlerte = ['rouge' => 'badge-danger', 'orange' => 'badge-warn', 'verte' => 'badge-success'][$niveau] ?? 'badge-gray';
    @endphp

    <table class="data">
        <tr>
            <td class="label">Niveau alerte</td>
            <td class="value"><span class="badge {{ $badgeAlerte }}">{{ strtoupper($niveau) }}</span></td>
            <td class="label">Date relance 1</td>
            <td class="value">{{ $porteurProj->date_relance_1?->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date mise en demeure</td>
            <td class="value">{{ $porteurProj->date_mise_en_demeure?->format('d/m/Y') ?: '—' }}</td>
            <td class="label">Date relance 2</td>
            <td class="value">{{ $porteurProj->date_relance_2?->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date résiliation</td>
            <td class="value">{{ $porteurProj->date_resiliation?->format('d/m/Y') ?: '—' }}</td>
            <td class="label"></td>
            <td class="value"></td>
        </tr>
    </table>
</div>

{{-- ═══════════ SECTION 6 — TERRAIN ═══════════ --}}
<div class="section section-6">
    <div class="section-title">6 · SUIVI TERRAIN</div>

    <table class="data">
        <tr>
            <td class="label">Date formation contractants</td>
            <td class="value">{{ $porteurProj->date_formation_contractants?->format('d/m/Y') ?: '—' }}</td>
            <td class="label">Date suivi terrain</td>
            <td class="value">{{ $porteurProj->date_suivi_terrain?->format('d/m/Y') ?: '—' }}</td>
        </tr>
    </table>

    @if($porteurProj->observation_suivi)
        <div style="margin-top: 10px; padding: 8px 12px; background: #fef3c7; border-left: 3px solid #f59e0b; font-size: 9.5px;">
            <b>Observation suivi :</b> {{ $porteurProj->observation_suivi }}
        </div>
    @endif
</div>

{{-- ═══════════ SECTION 7 — RAPPORT TECHNIQUE ═══════════ --}}
<div class="section section-7">
    <div class="section-title">7 · RAPPORT TECHNIQUE</div>

    <table class="data">
        <tr>
            <td class="label">Évaluateur</td>
            <td class="value"><b>{{ $porteurProj->evaluateur?->name ?: '—' }}</b></td>
            <td class="label">Date arrivée rapport</td>
            <td class="value">{{ $porteurProj->date_arrivee_rapport?->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date transfert évaluateur</td>
            <td class="value">{{ $porteurProj->date_transfert_evaluateur?->format('d/m/Y') ?: '—' }}</td>
            <td class="label">Date début traitement</td>
            <td class="value">{{ $porteurProj->date_debut_traitement?->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date validation</td>
            <td class="value">{{ $porteurProj->date_validation_evaluateur?->format('d/m/Y') ?: '—' }}</td>
            <td class="label">Date transmission DAF</td>
            <td class="value">{{ $porteurProj->date_transmission_daf?->format('d/m/Y') ?: '—' }}</td>
        </tr>
    </table>

    @if($porteurProj->reserve_description)
        <div style="margin-top: 10px; padding: 8px 12px; background: #ede9fe; border-left: 3px solid #7c3aed; font-size: 9.5px;">
            <b>Réserves :</b> {{ $porteurProj->reserve_description }}
        </div>
    @endif
</div>

{{-- ═══════════ SECTION 8 — RÉALISATION ═══════════ --}}
@php $benefReel = $porteurProj->benefRealise; @endphp
@if($benefReel || $benefPrevu)
<div class="section section-8">
    <div class="section-title">8 · RÉALISATION</div>

    @if($benefReel && $benefPrevu)
        {{-- Comparaison Prévu vs Réalisé --}}
        <div style="font-size: 10px; font-weight: 700; margin-bottom: 8px; color: #92400e;">
            Comparaison Prévu vs Réalisé
        </div>
        <table class="data">
            <thead>
                <tr>
                    <th></th>
                    <th class="text-right">Prévu</th>
                    <th class="text-right">Réalisé</th>
                    <th class="text-right">Écart</th>
                    <th class="text-right">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach([
                    ['Total', $benefPrevu->total, $benefReel->total],
                    ['Hommes', $benefPrevu->h, $benefReel->h],
                    ['Femmes', $benefPrevu->f, $benefReel->f],
                    ['Jeunes', $benefPrevu->jeunes, $benefReel->jeunes],
                    ['FPE', $benefPrevu->fpe, $benefReel->fpe],
                    ['Cadres', $benefPrevu->cadres, $benefReel->cadres],
                ] as [$lbl, $prevu, $reel])
                    @php
                        $ecart = (int)$reel - (int)$prevu;
                        $pct = $prevu > 0 ? round(($reel / $prevu) * 100) : 0;
                    @endphp
                    <tr>
                        <td><b>{{ $lbl }}</b></td>
                        <td class="text-right">{{ number_format((int)$prevu, 0, ',', ' ') }}</td>
                        <td class="text-right"><b>{{ number_format((int)$reel, 0, ',', ' ') }}</b></td>
                        <td class="text-right {{ $ecart >= 0 ? 'text-success' : '' }}" style="color: {{ $ecart >= 0 ? '#059669' : '#dc2626' }};">
                            {{ $ecart > 0 ? '+' : '' }}{{ $ecart }}
                        </td>
                        <td class="text-right">
                            <span class="badge {{ $pct >= 100 ? 'badge-success' : ($pct >= 80 ? 'badge-warn' : 'badge-danger') }}">{{ $pct }}%</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($benefReel)
        <div class="kpi-row">
            <div class="kpi-cell"><div class="kpi kpi-success"><div class="k-label">Total formé</div><div class="k-value">{{ (int)$benefReel->total }}</div></div></div>
            <div class="kpi-cell"><div class="kpi kpi-primary"><div class="k-label">H / F</div><div class="k-value">{{ (int)$benefReel->h }} / {{ (int)$benefReel->f }}</div></div></div>
            <div class="kpi-cell"><div class="kpi kpi-warning"><div class="k-label">Jeunes</div><div class="k-value">{{ (int)$benefReel->jeunes }}</div></div></div>
        </div>
    @endif
</div>
@endif

{{-- ═══════════ SIGNATURES ═══════════ --}}
<div class="signatures">
    <div class="sig-title">Signatures</div>
    <table class="sig-grid">
        <tr>
            <td>
                <div class="sig-role">Chargé de projet DEES</div>
                <div class="sig-line"></div>
                <div class="sig-date">Nom, date et signature</div>
            </td>
            <td>
                <div class="sig-role">Responsable DAF</div>
                <div class="sig-line"></div>
                <div class="sig-date">Nom, date et signature</div>
            </td>
            <td>
                <div class="sig-role">Directeur DEES</div>
                <div class="sig-line"></div>
                <div class="sig-date">Nom, date et signature</div>
            </td>
        </tr>
    </table>
</div>

</main>
</body>
</html>
