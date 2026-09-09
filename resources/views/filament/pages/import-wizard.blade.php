<x-filament-panels::page>

    {{-- ─────────────── ÉTAPE 1 : UPLOAD — Design sobre sans emojis ─────────────── --}}
    @if($etape === 'upload')
        <div class="seer-wizard-container">

            {{-- ═══ HERO CARD ═══ --}}
            <div class="seer-hero-card">
                <div class="seer-hero-badge">Assistant d'import intelligent</div>
                <h2 class="seer-hero-title">Prêt à importer vos données</h2>
                <p class="seer-hero-sub">
                    L'assistant SEER analyse votre fichier Excel avant l'import, détecte les référentiels inconnus
                    et vous propose des corrections intelligentes.
                </p>
                <div class="seer-hero-cta">
                    <p class="seer-cta-hint">Cliquez sur <b>« Sélectionner un fichier à analyser »</b> en haut à droite</p>
                </div>
            </div>

            {{-- ═══ 4 ÉTAPES VISUELLES ═══ --}}
            <h3 class="seer-section-title">Comment ça marche</h3>
            <div class="seer-steps">

                <div class="seer-step">
                    <div class="seer-step-num">1</div>
                    <div class="seer-step-title">Upload</div>
                    <div class="seer-step-desc">Vous sélectionnez votre fichier <code>TEMPLATE_IMPORT_DEES.xlsx</code> rempli.</div>
                </div>

                <div class="seer-step-arrow"></div>

                <div class="seer-step">
                    <div class="seer-step-num">2</div>
                    <div class="seer-step-title">Analyse</div>
                    <div class="seer-step-desc">SEER scanne les colonnes : Secteurs, Vagues, Guichets, Statuts, Régions.</div>
                </div>

                <div class="seer-step-arrow"></div>

                <div class="seer-step">
                    <div class="seer-step-num">3</div>
                    <div class="seer-step-title">Résolution</div>
                    <div class="seer-step-desc">Pour chaque valeur inconnue : mapper, créer, ou ignorer.</div>
                </div>

                <div class="seer-step-arrow"></div>

                <div class="seer-step">
                    <div class="seer-step-num">4</div>
                    <div class="seer-step-title">Import</div>
                    <div class="seer-step-desc">Récapitulatif, validation, puis import réel avec rapport détaillé.</div>
                </div>
            </div>

            {{-- ═══ AVANTAGES ═══ --}}
            <h3 class="seer-section-title">Ce qui rend SEER Import intelligent</h3>
            <div class="seer-features-grid">
                <div class="seer-feature">
                    <div class="seer-feature-mark"></div>
                    <div class="seer-feature-content">
                        <div class="seer-feature-title">Références souples</div>
                        <div class="seer-feature-desc"><code>STELLARIX_2026_001</code> = <code>stellarix-2026-001</code>. La casse et les séparateurs sont ignorés.</div>
                    </div>
                </div>

                <div class="seer-feature">
                    <div class="seer-feature-mark"></div>
                    <div class="seer-feature-content">
                        <div class="seer-feature-title">Détection fautes de frappe</div>
                        <div class="seer-feature-desc"><code>AGROALIMENAIRE</code> détecté comme faute de <code>AGROALIMENTAIRE</code> (similarité 92%).</div>
                    </div>
                </div>

                <div class="seer-feature">
                    <div class="seer-feature-mark"></div>
                    <div class="seer-feature-content">
                        <div class="seer-feature-title">Mémoire des choix</div>
                        <div class="seer-feature-desc">Vos décisions sont mémorisées. Le prochain import applique automatiquement les corrections.</div>
                    </div>
                </div>

                <div class="seer-feature">
                    <div class="seer-feature-mark"></div>
                    <div class="seer-feature-content">
                        <div class="seer-feature-title">Idempotent</div>
                        <div class="seer-feature-desc">Ré-importer le même fichier met à jour les données au lieu de dupliquer.</div>
                    </div>
                </div>

                <div class="seer-feature">
                    <div class="seer-feature-mark"></div>
                    <div class="seer-feature-content">
                        <div class="seer-feature-title">Rapport détaillé</div>
                        <div class="seer-feature-desc">Créés, mis à jour, rejetés, ignorés — chaque ligne est tracée et documentée.</div>
                    </div>
                </div>

                <div class="seer-feature">
                    <div class="seer-feature-mark"></div>
                    <div class="seer-feature-content">
                        <div class="seer-feature-title">Aucun risque</div>
                        <div class="seer-feature-desc">L'import réel ne démarre qu'après votre validation finale du récapitulatif.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ STYLES ═══ --}}
        <style>
            .seer-wizard-container { padding: 4px; }

            /* ─── HERO ─── */
            .seer-hero-card {
                background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%);
                border-radius: 20px;
                padding: 48px 40px;
                color: white;
                text-align: center;
                position: relative;
                overflow: hidden;
                margin-bottom: 40px;
                box-shadow: 0 20px 60px rgba(30, 64, 175, 0.25);
            }
            .seer-hero-card::before {
                content: '';
                position: absolute;
                inset: 0;
                background-image:
                    radial-gradient(circle at 20% 20%, rgba(255,255,255,0.15) 0%, transparent 40%),
                    radial-gradient(circle at 80% 80%, rgba(255,255,255,0.1) 0%, transparent 40%);
                pointer-events: none;
            }
            .seer-hero-badge {
                display: inline-block;
                background: rgba(255,255,255,0.15);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.25);
                border-radius: 20px;
                padding: 6px 16px;
                font-size: 0.75rem;
                font-weight: 700;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                margin-bottom: 20px;
                position: relative;
            }
            .seer-hero-title {
                font-size: 2rem;
                font-weight: 800;
                margin: 0 0 12px 0;
                letter-spacing: -0.02em;
                position: relative;
            }
            .seer-hero-sub {
                font-size: 1.05rem;
                opacity: 0.92;
                max-width: 600px;
                margin: 0 auto 24px auto;
                line-height: 1.55;
                position: relative;
            }
            .seer-hero-cta {
                background: rgba(255,255,255,0.15);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.25);
                border-radius: 12px;
                padding: 14px 24px;
                display: inline-block;
                position: relative;
            }
            .seer-cta-hint { margin: 0; font-size: 0.95rem; }

            /* ─── TITRES DE SECTION ─── */
            .seer-section-title {
                font-size: 1.15rem;
                font-weight: 700;
                margin: 40px 0 20px 0;
                color: rgb(17, 24, 39);
            }
            .dark .seer-section-title { color: #f9fafb; }

            /* ─── 4 ÉTAPES ─── */
            .seer-steps {
                display: flex;
                align-items: stretch;
                gap: 12px;
                margin-bottom: 40px;
                flex-wrap: wrap;
            }
            .seer-step {
                flex: 1;
                min-width: 180px;
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 16px;
                padding: 24px 20px;
                text-align: center;
                position: relative;
                box-shadow: 0 2px 6px rgba(0,0,0,0.04);
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .seer-step:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(30, 64, 175, 0.12);
                border-color: #93c5fd;
            }
            .seer-step-num {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%);
                color: white;
                font-weight: 800;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.3rem;
                margin: 0 auto 16px auto;
                box-shadow: 0 6px 16px rgba(30, 64, 175, 0.25);
                letter-spacing: -0.02em;
            }
            .seer-step-title {
                font-weight: 700;
                font-size: 1.05rem;
                color: rgb(17, 24, 39);
                margin-bottom: 6px;
            }
            .seer-step-desc {
                font-size: 0.85rem;
                color: rgb(107, 114, 128);
                line-height: 1.5;
            }
            .seer-step-arrow {
                display: flex;
                align-items: center;
                justify-content: center;
                min-width: 16px;
                position: relative;
            }
            .seer-step-arrow::before {
                content: '';
                width: 20px;
                height: 2px;
                background: linear-gradient(90deg, #93c5fd, #c4b5fd);
                border-radius: 2px;
            }
            .seer-step-arrow::after {
                content: '';
                position: absolute;
                right: -2px;
                width: 8px;
                height: 8px;
                border-top: 2px solid #c4b5fd;
                border-right: 2px solid #c4b5fd;
                transform: rotate(45deg);
            }
            .dark .seer-step {
                background: rgb(31, 41, 55);
                border-color: rgb(55, 65, 81);
            }
            .dark .seer-step-title { color: #f9fafb; }
            .dark .seer-step-desc { color: rgb(156, 163, 175); }

            /* ─── FEATURES ─── */
            .seer-features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 16px;
                margin-bottom: 32px;
            }
            .seer-feature {
                display: flex;
                gap: 14px;
                padding: 18px;
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                transition: border-color 0.2s;
            }
            .seer-feature:hover { border-color: #93c5fd; }
            .seer-feature-mark {
                flex-shrink: 0;
                width: 4px;
                border-radius: 2px;
                background: linear-gradient(180deg, #1e40af, #7c3aed);
                min-height: 40px;
            }
            .seer-feature-content { flex: 1; }
            .seer-feature-title {
                font-weight: 700;
                font-size: 0.95rem;
                color: rgb(17, 24, 39);
                margin-bottom: 4px;
            }
            .seer-feature-desc {
                font-size: 0.85rem;
                color: rgb(107, 114, 128);
                line-height: 1.5;
            }
            .seer-feature-desc code {
                font-family: 'Consolas', 'Monaco', monospace;
                background: rgba(30, 64, 175, 0.08);
                color: rgb(30, 64, 175);
                padding: 1px 6px;
                border-radius: 4px;
                font-size: 0.8rem;
                font-weight: 600;
            }
            .dark .seer-feature {
                background: rgb(31, 41, 55);
                border-color: rgb(55, 65, 81);
            }
            .dark .seer-feature-title { color: #f9fafb; }
            .dark .seer-feature-desc { color: rgb(156, 163, 175); }
            .dark .seer-feature-desc code {
                background: rgba(96, 165, 250, 0.15);
                color: rgb(147, 197, 253);
            }

            /* ─── Responsive ─── */
            @media (max-width: 768px) {
                .seer-hero-title { font-size: 1.5rem; }
                .seer-hero-sub { font-size: 0.95rem; }
                .seer-steps { flex-direction: column; }
                .seer-step-arrow { transform: rotate(90deg); }
            }
        </style>
    @endif

    {{-- ═══════════════ ÉTAPE 2 : RÉSOLUTION — Design clair et guidé ═══════════════ --}}
    @if($etape === 'resolution')
        @php
            $ref = $this->refActuel;
            $refLabel = $this->refLabel;
            $inconnus = $this->inconnusActuels;
            $existants = $this->existantsActuels;
            $totalRef = count($refKeys);
            $progressPct = round((($refIndex + 1) / $totalRef) * 100);
        @endphp

        {{-- ═══ Progress bar en haut ═══ --}}
        <div class="seer-progress-container">
            <div class="seer-progress-header">
                <div>
                    <div class="seer-progress-step">Étape {{ $refIndex + 2 }} sur {{ $totalRef + 2 }}</div>
                    <div class="seer-progress-title">Résoudre les {{ str_replace(['🏭 ', '🌊 ', '🏢 ', '📊 ', '🗺️ '], '', $refLabel) }}</div>
                </div>
                <div class="seer-progress-badge">{{ count($inconnus) }} à traiter</div>
            </div>
            <div class="seer-progress-bar">
                <div class="seer-progress-fill" style="width: {{ $progressPct }}%"></div>
            </div>
        </div>

        {{-- ═══ Explication de l'étape ═══ --}}
        <div class="seer-explainer">
            <div class="seer-explainer-title">Que faire sur cette page ?</div>
            <p>
                SEER a trouvé <b>{{ count($inconnus) }} valeur(s) inconnue(s)</b> dans votre fichier Excel.
                Pour chacune, dites-nous ce que vous voulez faire — 3 choix possibles.
            </p>
        </div>

        {{-- ═══ Cartes de résolution ═══ --}}
        @foreach($inconnus as $valeur => $item)
            @php
                $currentAction = $this->resolutions[$ref][$valeur]['action'] ?? null;
                $nbLignes = $item['nb_lignes'] ?? count($item['lignes']);
                $lignesTxt = implode(', ', array_slice($item['lignes'], 0, 5));
                if (count($item['lignes']) > 5) $lignesTxt .= '…';
            @endphp

            <div class="seer-unknown-card">
                {{-- Titre : la valeur inconnue --}}
                <div class="seer-unknown-header">
                    @php
                        $nbVariantes = isset($item['variantes']) ? count($item['variantes']) : 1;
                    @endphp
                    <div class="seer-unknown-tag">
                        Valeur inconnue
                        @if($nbVariantes > 1)
                            <span class="seer-variantes-badge">{{ $nbVariantes }} variantes équivalentes</span>
                        @endif
                    </div>
                    <div class="seer-unknown-value">« {{ $valeur }} »</div>
                    <div class="seer-unknown-lines">Trouvée sur <b>{{ $nbLignes }} ligne(s)</b> ({{ $lignesTxt }})</div>

                    @if($nbVariantes > 1)
                        <div class="seer-variantes-panel">
                            <div class="seer-variantes-title">Ces {{ $nbVariantes }} orthographes sont équivalentes après normalisation :</div>
                            <div class="seer-variantes-list">
                                @foreach($item['variantes'] as $variant => $nbLg)
                                    <span class="seer-variante-item">« {{ $variant }} » <em>({{ $nbLg }} ligne{{ $nbLg > 1 ? 's' : '' }})</em></span>
                                @endforeach
                            </div>
                            <div class="seer-variantes-hint">Une seule décision ci-dessous s'applique aux <b>{{ $nbLignes }} lignes</b>.</div>
                        </div>
                    @endif

                    {{-- ═══ APERÇU des lignes concernées (lecture seule) ═══ --}}
                    @if(! empty($item['preview_lignes']))
                        <details class="seer-preview-details">
                            <summary class="seer-preview-toggle">
                                <span class="seer-preview-icon">▶</span>
                                <span>Voir les {{ min(count($item['preview_lignes']), $item['preview_max'] ?? 20) }}
                                @if($nbLignes > ($item['preview_max'] ?? 20))
                                    premières
                                @endif
                                lignes concernées</span>
                                @if($nbLignes > ($item['preview_max'] ?? 20))
                                    <span class="seer-preview-count-badge">Sur {{ $nbLignes }} au total</span>
                                @endif
                            </summary>

                            <div class="seer-preview-table-wrap">
                                <table class="seer-preview-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">Ligne</th>
                                            <th style="width: 22%;">Réf. projet</th>
                                            <th style="width: 22%;">Réf. convention</th>
                                            <th style="width: 22%;">Porteur</th>
                                            <th>Intitulé projet</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($item['preview_lignes'] as $ligne)
                                            <tr>
                                                <td class="seer-preview-num">{{ $ligne['num_ligne'] }}</td>
                                                <td>{{ $ligne['ref_projet'] ?: '—' }}</td>
                                                <td>{{ $ligne['ref_conv'] ?: '—' }}</td>
                                                <td>{{ $ligne['porteur'] ?: '—' }}</td>
                                                <td>{{ $ligne['intitule'] ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @if($nbLignes > count($item['preview_lignes']))
                                    <div class="seer-preview-more">
                                        + <b>{{ $nbLignes - count($item['preview_lignes']) }}</b> autres lignes non affichées ici mais concernées par votre décision.
                                    </div>
                                @endif
                                <div class="seer-preview-hint">
                                    💡 <b>Pour corriger une ligne spécifique</b> : ouvrez votre fichier Excel, modifiez la valeur, puis relancez l'import.
                                    Aucune modification n'est possible directement ici — c'est un aperçu de vérification.
                                </div>
                            </div>
                        </details>
                    @endif
                </div>

                {{-- 3 grandes cartes de choix --}}
                <div class="seer-choices">

                    {{-- CHOIX 1 : Utiliser une valeur existante --}}
                    <div class="seer-choice seer-choice-blue {{ in_array($currentAction, ['map', 'map_libre']) ? 'is-selected' : '' }}">
                        <div class="seer-choice-header">
                            <div class="seer-choice-num">1</div>
                            <div>
                                <div class="seer-choice-title">Utiliser une valeur existante</div>
                                <div class="seer-choice-sub">C'est une faute de frappe / synonyme d'un référentiel déjà en base</div>
                            </div>
                        </div>

                        @if(! empty($item['suggestions']))
                            <div class="seer-choice-body">
                                <div class="seer-choice-hint">SEER a trouvé ces correspondances possibles :</div>
                                @foreach($item['suggestions'] as $sug)
                                    <label class="seer-suggestion">
                                        <input type="radio"
                                            wire:model.live="resolutions.{{ $ref }}.{{ $valeur }}.action"
                                            @change="$wire.set('resolutions.{{ $ref }}.{{ $valeur }}.target_id', {{ $sug['id'] }})"
                                            value="map">
                                        <span class="seer-suggestion-content">
                                            <span class="seer-suggestion-name">{{ $sug['libelle'] }}</span>
                                            <span class="seer-similarity-badge seer-sim-{{ $sug['similarite'] >= 90 ? 'high' : ($sug['similarite'] >= 75 ? 'mid' : 'low') }}">
                                                {{ $sug['similarite'] }}% similaire
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        {{-- Choix libre depuis une liste --}}
                        <div class="seer-choice-body">
                            <div class="seer-choice-hint">Ou choisissez dans la liste complète :</div>
                            <label class="seer-suggestion">
                                <input type="radio"
                                    wire:model.live="resolutions.{{ $ref }}.{{ $valeur }}.action"
                                    value="map_libre">
                                <select wire:model.live="resolutions.{{ $ref }}.{{ $valeur }}.target_id" class="seer-choice-select">
                                    <option value="">— Sélectionner une valeur —</option>
                                    @foreach($existants as $id => $lbl)
                                        <option value="{{ $id }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    {{-- CHOIX 2 : Créer nouveau --}}
                    <label class="seer-choice seer-choice-green {{ $currentAction === 'create' ? 'is-selected' : '' }}">
                        <input type="radio" wire:model.live="resolutions.{{ $ref }}.{{ $valeur }}.action" value="create" style="display:none">
                        <div class="seer-choice-header">
                            <div class="seer-choice-num">2</div>
                            <div>
                                <div class="seer-choice-title">Créer comme nouveau référentiel</div>
                                <div class="seer-choice-sub">C'est une vraie nouvelle valeur qui n'existait pas encore</div>
                            </div>
                        </div>
                        <div class="seer-choice-body">
                            <div class="seer-create-preview">
                                <div class="seer-create-label">SEER va créer avec le libellé normalisé :</div>
                                <div class="seer-create-value">{{ $item['libelle_seer'] ?? $valeur }}</div>
                                @if(($item['libelle_seer'] ?? $valeur) !== $valeur)
                                    <div class="seer-create-explain">
                                        Convention SEER : UPPER_SNAKE_CASE (sans accents ni caractères spéciaux)
                                    </div>
                                @endif
                                @php $libelleLen = strlen($item['libelle_seer'] ?? $valeur); @endphp
                                @if($libelleLen > 60)
                                    <div class="seer-create-warning">
                                        <b>⚠ Attention</b> — Ce libellé fait <b>{{ $libelleLen }} caractères</b>. Il semble contenir <b>plusieurs valeurs collées</b> dans une seule cellule Excel.
                                        <br>
                                        <br>
                                        <b>Conseil :</b> ouvrez le fichier Excel, séparez ces valeurs sur plusieurs lignes (une par secteur), puis relancez l'import. Sinon SEER créera <b>un seul référentiel</b> avec ce nom très long.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </label>

                    {{-- CHOIX 3 : Ignorer --}}
                    <label class="seer-choice seer-choice-red {{ $currentAction === 'ignore' ? 'is-selected' : '' }}">
                        <input type="radio" wire:model.live="resolutions.{{ $ref }}.{{ $valeur }}.action" value="ignore" style="display:none">
                        <div class="seer-choice-header">
                            <div class="seer-choice-num">3</div>
                            <div>
                                <div class="seer-choice-title">Ignorer ces lignes</div>
                                <div class="seer-choice-sub">C'est une erreur de saisie, ne pas importer les lignes concernées</div>
                            </div>
                        </div>
                        <div class="seer-choice-body">
                            <div class="seer-ignore-warn">
                                <b>{{ $nbLignes }} ligne(s)</b> du fichier Excel ne seront pas importées.
                            </div>
                        </div>
                    </label>

                </div>

                {{-- Mémoire du choix --}}
                <div class="seer-memory">
                    <label class="seer-memory-label">
                        <input type="checkbox" wire:model="resolutions.{{ $ref }}.{{ $valeur }}.memoriser">
                        <span>
                            <b>Se souvenir de ce choix</b> — la prochaine fois qu'un fichier contient « {{ $valeur }} », SEER appliquera automatiquement la même décision.
                        </span>
                    </label>
                </div>
            </div>
        @endforeach

        {{-- ═══ Boutons de navigation ═══ --}}
        <div class="seer-nav-buttons">
            <button type="button" wire:click="retourResolutionPrecedente" class="seer-btn-secondary">
                ← Retour
            </button>
            <button type="button" wire:click="validerResolutionActuelle" class="seer-btn-primary">
                Valider ces choix et continuer →
            </button>
        </div>

        {{-- ═══ Styles pour cette étape ═══ --}}
        <style>
            /* ─── Progress bar ─── */
            .seer-progress-container {
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 14px;
                padding: 20px 24px;
                margin-bottom: 20px;
            }
            .dark .seer-progress-container { background: rgb(31, 41, 55); border-color: rgb(55, 65, 81); }
            .seer-progress-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 14px;
            }
            .seer-progress-step {
                font-size: 0.75rem;
                font-weight: 700;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin-bottom: 4px;
            }
            .seer-progress-title {
                font-size: 1.4rem;
                font-weight: 800;
                color: rgb(17, 24, 39);
                letter-spacing: -0.01em;
            }
            .dark .seer-progress-title { color: #f9fafb; }
            .seer-progress-badge {
                background: linear-gradient(135deg, #f59e0b, #f97316);
                color: white;
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 800;
                box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
            }
            .seer-progress-bar {
                height: 8px;
                background: #f3f4f6;
                border-radius: 4px;
                overflow: hidden;
            }
            .dark .seer-progress-bar { background: rgb(55, 65, 81); }
            .seer-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #1e40af, #7c3aed);
                border-radius: 4px;
                transition: width 0.4s ease;
            }

            /* ─── Explainer box ─── */
            .seer-explainer {
                background: linear-gradient(135deg, #dbeafe, #ede9fe);
                border: 1px solid #93c5fd;
                border-radius: 14px;
                padding: 16px 20px;
                margin-bottom: 20px;
            }
            .dark .seer-explainer {
                background: linear-gradient(135deg, rgba(30, 64, 175, 0.15), rgba(124, 58, 237, 0.15));
                border-color: rgba(59, 130, 246, 0.3);
            }
            .seer-explainer-title {
                font-weight: 800;
                color: #1e40af;
                margin-bottom: 6px;
                font-size: 0.95rem;
            }
            .dark .seer-explainer-title { color: #93c5fd; }
            .seer-explainer p {
                font-size: 0.9rem;
                color: rgb(55, 65, 81);
                line-height: 1.55;
                margin: 0;
            }
            .dark .seer-explainer p { color: rgb(209, 213, 219); }

            /* ─── Carte "valeur inconnue" ─── */
            .seer-unknown-card {
                background: white;
                border: 2px solid #fef3c7;
                border-radius: 16px;
                padding: 24px;
                margin-bottom: 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            }
            .dark .seer-unknown-card { background: rgb(31, 41, 55); border-color: rgba(245, 158, 11, 0.3); }
            .seer-unknown-header { margin-bottom: 18px; }
            .seer-unknown-tag {
                display: inline-block;
                background: #fef3c7;
                color: #b45309;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.7rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin-bottom: 10px;
            }
            .dark .seer-unknown-tag { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
            .seer-unknown-value {
                font-size: 1.6rem;
                font-weight: 900;
                color: rgb(17, 24, 39);
                margin-bottom: 6px;
                letter-spacing: -0.01em;
            }
            .dark .seer-unknown-value { color: #f9fafb; }
            .seer-unknown-lines {
                font-size: 0.85rem;
                color: rgb(107, 114, 128);
            }

            /* ─── Panel variantes équivalentes ─── */
            .seer-variantes-badge {
                display: inline-block;
                background: linear-gradient(135deg, #1e40af, #7c3aed);
                color: white;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 0.7rem;
                font-weight: 700;
                margin-left: 8px;
                letter-spacing: 0.03em;
            }
            .seer-variantes-panel {
                margin-top: 14px;
                background: linear-gradient(135deg, rgba(30, 64, 175, 0.06), rgba(124, 58, 237, 0.06));
                border: 1px dashed #93c5fd;
                border-radius: 10px;
                padding: 12px 14px;
            }
            .dark .seer-variantes-panel {
                background: linear-gradient(135deg, rgba(30, 64, 175, 0.15), rgba(124, 58, 237, 0.15));
                border-color: rgba(96, 165, 250, 0.4);
            }
            .seer-variantes-title {
                font-size: 0.8rem;
                font-weight: 700;
                color: #1e40af;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .dark .seer-variantes-title { color: #93c5fd; }
            .seer-variantes-list {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-bottom: 10px;
            }
            .seer-variante-item {
                display: inline-block;
                background: white;
                border: 1px solid #dbeafe;
                border-radius: 6px;
                padding: 4px 10px;
                font-size: 0.82rem;
                color: rgb(30, 64, 175);
                font-family: 'Consolas', 'Monaco', monospace;
            }
            .dark .seer-variante-item {
                background: rgb(31, 41, 55);
                border-color: rgba(96, 165, 250, 0.3);
                color: #93c5fd;
            }
            .seer-variante-item em {
                font-style: normal;
                color: rgb(107, 114, 128);
                font-size: 0.75rem;
                margin-left: 4px;
            }
            .seer-variantes-hint {
                font-size: 0.8rem;
                color: rgb(75, 85, 99);
                font-style: italic;
            }
            .dark .seer-variantes-hint { color: rgb(156, 163, 175); }

            /* ─── Aperçu des lignes concernées ─── */
            .seer-preview-details {
                margin-top: 14px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: white;
                overflow: hidden;
            }
            .dark .seer-preview-details {
                background: rgb(17, 24, 39);
                border-color: rgb(55, 65, 81);
            }
            .seer-preview-details[open] { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

            .seer-preview-toggle {
                cursor: pointer;
                padding: 12px 16px;
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.9rem;
                font-weight: 600;
                color: #1e40af;
                background: linear-gradient(90deg, #eff6ff 0%, #ffffff 100%);
                user-select: none;
                list-style: none;
            }
            .seer-preview-toggle::-webkit-details-marker { display: none; }
            .dark .seer-preview-toggle {
                background: linear-gradient(90deg, rgba(30, 64, 175, 0.15), rgb(17, 24, 39));
                color: #93c5fd;
            }
            .seer-preview-toggle:hover { background: linear-gradient(90deg, #dbeafe 0%, #f9fafb 100%); }
            .dark .seer-preview-toggle:hover { background: linear-gradient(90deg, rgba(30, 64, 175, 0.25), rgb(31, 41, 55)); }

            .seer-preview-icon {
                display: inline-block;
                transition: transform 0.2s;
                font-size: 0.7rem;
                color: #1e40af;
            }
            .seer-preview-details[open] .seer-preview-icon { transform: rotate(90deg); }

            .seer-preview-count-badge {
                margin-left: auto;
                background: linear-gradient(135deg, #1e40af, #7c3aed);
                color: white;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 700;
            }

            .seer-preview-table-wrap {
                padding: 12px 16px 16px;
                border-top: 1px solid #e5e7eb;
                overflow-x: auto;
            }
            .dark .seer-preview-table-wrap { border-top-color: rgb(55, 65, 81); }

            .seer-preview-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.8rem;
            }
            .seer-preview-table th {
                background: #f3f4f6;
                padding: 8px 10px;
                text-align: left;
                font-weight: 700;
                color: rgb(75, 85, 99);
                text-transform: uppercase;
                font-size: 0.7rem;
                letter-spacing: 0.05em;
                border-bottom: 2px solid #d1d5db;
            }
            .dark .seer-preview-table th {
                background: rgb(31, 41, 55);
                color: rgb(156, 163, 175);
                border-bottom-color: rgb(55, 65, 81);
            }
            .seer-preview-table td {
                padding: 8px 10px;
                border-bottom: 1px solid #f3f4f6;
                color: rgb(31, 41, 55);
            }
            .dark .seer-preview-table td {
                border-bottom-color: rgb(55, 65, 81);
                color: rgb(209, 213, 219);
            }
            .seer-preview-table tr:hover td {
                background: #f9fafb;
            }
            .dark .seer-preview-table tr:hover td { background: rgb(31, 41, 55); }
            .seer-preview-num {
                font-family: 'Consolas', 'Monaco', monospace;
                font-weight: 700;
                color: #1e40af;
                text-align: center;
                background: #eff6ff;
            }
            .dark .seer-preview-num {
                background: rgba(30, 64, 175, 0.15);
                color: #93c5fd;
            }

            .seer-preview-more {
                margin-top: 10px;
                padding: 8px 12px;
                background: linear-gradient(135deg, #fef3c7, #fef9c3);
                border-left: 3px solid #f59e0b;
                border-radius: 6px;
                font-size: 0.8rem;
                color: #92400e;
            }
            .dark .seer-preview-more {
                background: rgba(245, 158, 11, 0.15);
                color: #fbbf24;
            }

            .seer-preview-hint {
                margin-top: 10px;
                padding: 8px 12px;
                background: #f3f4f6;
                border-radius: 6px;
                font-size: 0.8rem;
                color: rgb(75, 85, 99);
                line-height: 1.5;
            }
            .dark .seer-preview-hint {
                background: rgb(31, 41, 55);
                color: rgb(156, 163, 175);
            }

            /* ─── Grille des 3 choix ─── */
            .seer-choices {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 14px;
            }

            /* ─── Carte "choix" ─── */
            .seer-choice {
                background: #f9fafb;
                border: 2px solid #e5e7eb;
                border-radius: 12px;
                padding: 16px;
                cursor: pointer;
                transition: all 0.2s;
                display: block;
            }
            .dark .seer-choice { background: rgb(17, 24, 39); border-color: rgb(55, 65, 81); }
            .seer-choice:hover {
                border-color: #93c5fd;
                box-shadow: 0 4px 12px rgba(30, 64, 175, 0.1);
                transform: translateY(-2px);
            }
            .seer-choice.is-selected.seer-choice-blue {
                border-color: #1e40af;
                background: #dbeafe;
                box-shadow: 0 6px 16px rgba(30, 64, 175, 0.2);
            }
            .seer-choice.is-selected.seer-choice-green {
                border-color: #059669;
                background: #d1fae5;
                box-shadow: 0 6px 16px rgba(5, 150, 105, 0.2);
            }
            .seer-choice.is-selected.seer-choice-red {
                border-color: #dc2626;
                background: #fee2e2;
                box-shadow: 0 6px 16px rgba(220, 38, 38, 0.2);
            }
            .dark .seer-choice.is-selected.seer-choice-blue { background: rgba(30, 64, 175, 0.2); }
            .dark .seer-choice.is-selected.seer-choice-green { background: rgba(5, 150, 105, 0.2); }
            .dark .seer-choice.is-selected.seer-choice-red { background: rgba(220, 38, 38, 0.2); }

            .seer-choice-header {
                display: flex;
                gap: 12px;
                margin-bottom: 12px;
            }
            .seer-choice-num {
                flex-shrink: 0;
                width: 32px;
                height: 32px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 900;
                color: white;
                font-size: 1rem;
            }
            .seer-choice-blue .seer-choice-num { background: #1e40af; }
            .seer-choice-green .seer-choice-num { background: #059669; }
            .seer-choice-red .seer-choice-num { background: #dc2626; }

            .seer-choice-title {
                font-weight: 700;
                font-size: 0.95rem;
                color: rgb(17, 24, 39);
                line-height: 1.3;
            }
            .dark .seer-choice-title { color: #f9fafb; }
            .seer-choice-sub {
                font-size: 0.8rem;
                color: rgb(107, 114, 128);
                margin-top: 2px;
                line-height: 1.4;
            }
            .seer-choice-body { margin-top: 8px; }
            .seer-choice-hint {
                font-size: 0.75rem;
                color: rgb(107, 114, 128);
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 6px;
            }

            /* ─── Suggestions ─── */
            .seer-suggestion {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 10px;
                border-radius: 8px;
                cursor: pointer;
                margin-bottom: 4px;
                background: white;
                border: 1px solid #e5e7eb;
                transition: all 0.15s;
            }
            .dark .seer-suggestion { background: rgb(31, 41, 55); border-color: rgb(55, 65, 81); }
            .seer-suggestion:hover {
                border-color: #93c5fd;
                background: #f0f9ff;
            }
            .dark .seer-suggestion:hover { background: rgba(30, 64, 175, 0.15); }
            .seer-suggestion input[type="radio"] { flex-shrink: 0; }
            .seer-suggestion-content {
                flex: 1;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
            }
            .seer-suggestion-name {
                font-weight: 600;
                font-size: 0.9rem;
                color: rgb(17, 24, 39);
            }
            .dark .seer-suggestion-name { color: #f9fafb; }
            .seer-similarity-badge {
                font-size: 0.7rem;
                font-weight: 800;
                padding: 3px 8px;
                border-radius: 12px;
            }
            .seer-sim-high { background: #d1fae5; color: #065f46; }
            .seer-sim-mid { background: #fef3c7; color: #b45309; }
            .seer-sim-low { background: #f3f4f6; color: #4b5563; }

            .seer-choice-select {
                flex: 1;
                padding: 6px 10px;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                font-size: 0.85rem;
                background: white;
            }
            .dark .seer-choice-select { background: rgb(31, 41, 55); color: white; border-color: rgb(55, 65, 81); }

            /* ─── Preview création ─── */
            .seer-create-preview {
                background: white;
                border: 1px dashed #059669;
                border-radius: 8px;
                padding: 12px;
                text-align: center;
            }
            .dark .seer-create-preview { background: rgb(31, 41, 55); }
            .seer-create-label {
                font-size: 0.7rem;
                color: #059669;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                margin-bottom: 4px;
            }
            .seer-create-value {
                font-size: 0.95rem;
                font-weight: 800;
                color: rgb(17, 24, 39);
                font-family: 'Consolas', 'Monaco', monospace;
                letter-spacing: 0.02em;
                /* ─── Responsive : wrap si trop long ─── */
                word-break: break-all;
                overflow-wrap: anywhere;
                max-width: 100%;
                line-height: 1.4;
                text-align: center;
            }
            .dark .seer-create-value { color: #f9fafb; }
            .seer-create-explain {
                margin-top: 6px;
                font-size: 0.7rem;
                color: rgb(107, 114, 128);
                font-style: italic;
            }
            .dark .seer-create-explain { color: rgb(156, 163, 175); }
            .seer-create-warning {
                margin-top: 10px;
                padding: 8px 10px;
                background: #fef3c7;
                border-left: 3px solid #f59e0b;
                border-radius: 6px;
                font-size: 0.75rem;
                color: #92400e;
                text-align: left;
                line-height: 1.4;
            }
            .dark .seer-create-warning {
                background: rgba(245, 158, 11, 0.15);
                color: #fbbf24;
            }

            /* ─── Avertissement ignore ─── */
            .seer-ignore-warn {
                background: white;
                border: 1px solid #fecaca;
                border-radius: 8px;
                padding: 12px;
                font-size: 0.85rem;
                color: #991b1b;
                text-align: center;
            }
            .dark .seer-ignore-warn { background: rgba(220, 38, 38, 0.1); border-color: rgba(220, 38, 38, 0.3); color: #fca5a5; }

            /* ─── Mémoire ─── */
            .seer-memory {
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid #e5e7eb;
            }
            .dark .seer-memory { border-top-color: rgb(55, 65, 81); }
            .seer-memory-label {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                cursor: pointer;
                font-size: 0.85rem;
                color: rgb(75, 85, 99);
                line-height: 1.5;
            }
            .dark .seer-memory-label { color: rgb(156, 163, 175); }
            .seer-memory-label input[type="checkbox"] { margin-top: 2px; flex-shrink: 0; }
            .seer-memory-label b { color: rgb(17, 24, 39); }
            .dark .seer-memory-label b { color: #f9fafb; }

            /* ─── Boutons de navigation ─── */
            .seer-nav-buttons {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                margin-top: 24px;
                position: sticky;
                bottom: 16px;
                background: white;
                padding: 16px;
                border-radius: 14px;
                box-shadow: 0 -4px 12px rgba(0,0,0,0.06);
                border: 1px solid #e5e7eb;
            }
            .dark .seer-nav-buttons { background: rgb(31, 41, 55); border-color: rgb(55, 65, 81); }
            .seer-btn-primary {
                background: linear-gradient(135deg, #1e40af, #7c3aed);
                color: white;
                padding: 12px 28px;
                border: none;
                border-radius: 10px;
                font-weight: 700;
                font-size: 0.95rem;
                cursor: pointer;
                box-shadow: 0 6px 16px rgba(30, 64, 175, 0.25);
                transition: transform 0.15s;
            }
            .seer-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(30, 64, 175, 0.35); }
            .seer-btn-secondary {
                background: #f3f4f6;
                color: rgb(75, 85, 99);
                padding: 12px 20px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                font-weight: 600;
                font-size: 0.95rem;
                cursor: pointer;
            }
            .seer-btn-secondary:hover { background: #e5e7eb; }
            .dark .seer-btn-secondary { background: rgb(55, 65, 81); color: rgb(209, 213, 219); border-color: rgb(75, 85, 99); }
        </style>
    @endif

    {{-- ─────────────── ÉTAPE 3 : RÉCAPITULATIF ─────────────── --}}
    @if($etape === 'recap')
        <div class="fi-section bg-white dark:bg-gray-900 rounded-xl p-6 ring-1 ring-gray-950/5 dark:ring-white/10">
            <h2 class="text-lg font-bold mb-4">✅ Récapitulatif — Prêt à importer</h2>

            <div class="grid gap-4">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
                    <div class="font-semibold text-green-800 dark:text-green-300">
                        📊 {{ $scan['total_lignes'] ?? 0 }} lignes à traiter
                    </div>
                    @if(($scan['total_inconnus'] ?? 0) > 0)
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ $scan['total_inconnus'] }} inconnu(s) résolu(s)
                        </div>
                    @else
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Aucun inconnu — tous les référentiels sont reconnus ✅
                        </div>
                    @endif
                </div>

                @foreach($resolutions as $ref => $decisions)
                    @if(! empty($decisions))
                        <div class="border rounded-lg p-4">
                            <h3 class="font-semibold mb-2 capitalize">{{ $ref }}</h3>
                            <ul class="text-sm space-y-1">
                                @foreach($decisions as $val => $dec)
                                    <li class="flex items-center gap-2">
                                        @if($dec['action'] === 'map' || $dec['action'] === 'map_libre')
                                            <span class="text-blue-600">✏️</span>
                                            <span>"{{ $val }}" → <b>{{ $dec['target_label'] ?? 'existant' }}</b></span>
                                        @elseif($dec['action'] === 'create')
                                            <span class="text-green-600">➕</span>
                                            <span>Créer nouveau <b>"{{ $val }}"</b></span>
                                        @else
                                            <span class="text-red-600">🚫</span>
                                            <span>Ignorer "{{ $val }}"</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-6 flex justify-between">
                <button type="button" wire:click="recommencer"
                    class="rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 px-4 py-2 text-sm">
                    ← Annuler
                </button>
                <button type="button" wire:click="lancerImportReel"
                    wire:loading.attr="disabled"
                    class="rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 text-sm font-semibold">
                    <span wire:loading.remove>🚀 Lancer l'import</span>
                    <span wire:loading>⏳ Import en cours...</span>
                </button>
            </div>
        </div>
    @endif

    {{-- ─────────────── ÉTAPE 4 : TERMINÉ ─────────────── --}}
    @if($etape === 'done')
        <div class="fi-section bg-white dark:bg-gray-900 rounded-xl p-6 ring-1 ring-gray-950/5 dark:ring-white/10">
            <h2 class="text-2xl font-bold mb-4">🎉 Import terminé !</h2>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-green-700">{{ $rapportImport['projets'] ?? 0 }}</div>
                    <div class="text-sm text-gray-600">Projets importés</div>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-blue-700">{{ $rapportImport['partenaires'] ?? 0 }}</div>
                    <div class="text-sm text-gray-600">Partenaires</div>
                </div>
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-orange-700">{{ $rapportImport['doublons'] ?? 0 }}</div>
                    <div class="text-sm text-gray-600">Doublons fusionnés</div>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-red-700">{{ $rapportImport['ignores'] ?? 0 }}</div>
                    <div class="text-sm text-gray-600">Lignes ignorées</div>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="button" wire:click="recommencer"
                    class="rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 text-sm font-semibold">
                    Nouvel import
                </button>
                <a href="/admin/porteur-projs"
                    class="rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 px-4 py-2 text-sm">
                    Voir les projets importés →
                </a>
            </div>
        </div>
    @endif
</x-filament-panels::page>
