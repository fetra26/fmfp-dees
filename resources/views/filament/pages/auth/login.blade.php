<x-filament-panels::page.simple>
    <div class="seer-login-wrapper">

        {{-- ═══════════════ HERO GAUCHE ═══════════════ --}}
        <div class="seer-hero">
            <div class="seer-brand">
                <div class="seer-brand-logo">
                    @include('filament.components.logo-seer', ['size' => 56])
                </div>
                <div class="seer-brand-name">SEER</div>
            </div>

            <div class="seer-hero-content">
                <h1 class="seer-title">
                    Voir clair<br>
                    <span class="seer-title-accent">dans chaque projet.</span>
                </h1>

                <p class="seer-tagline">
                    La plateforme de suivi-évaluation des projets de formation professionnelle du FMFP.
                    Pour la DEES et ses équipes.
                </p>

                <div class="seer-features">
                    <div class="seer-feature">
                        <span class="seer-feature-icon">🎯</span>
                        <span>Suivi temps réel</span>
                    </div>
                    <div class="seer-feature">
                        <span class="seer-feature-icon">📊</span>
                        <span>Analytics avancés</span>
                    </div>
                    <div class="seer-feature">
                        <span class="seer-feature-icon">🔒</span>
                        <span>Sécurisé & audité</span>
                    </div>
                </div>
            </div>

            <div class="seer-footer">
                <div>FMFP · Direction des Études et du Suivi-Évaluation</div>
                <div class="seer-footer-acronym">Suivi · Évaluation · Études · Rapports</div>
            </div>
        </div>

        {{-- ═══════════════ FORMULAIRE DROITE (Filament natif) ═══════════════ --}}
        <div class="seer-form-panel">
            <div class="seer-form-card">
                <div class="seer-form-header">
                    <h2 class="seer-form-title">Connexion</h2>
                    <p class="seer-form-sub">Accédez à votre espace de suivi</p>
                </div>

                {{-- Filament rend automatiquement le formulaire d'auth ici --}}
                {{ $this->content }}

                <div class="seer-form-footer">
                    Un problème de connexion ?
                    <a href="mailto:support@fmfp.mg">Contacter le support</a>
                </div>
            </div>
        </div>

    </div>

    {{-- ═══════════════ STYLES CUSTOM SEER ═══════════════ --}}
    @push('styles')
    <style>
        /* ─── Cache la chrome Filament (heading, subheading, logo) ─── */
        .fi-simple-page-header,
        .fi-simple-page-heading,
        .fi-simple-page-subheading,
        header.fi-simple-header,
        .fi-simple-layout > .fi-logo,
        [class*="fi-simple-page"] > h1.fi-header-heading,
        [class*="fi-simple-page"] > p.fi-header-subheading {
            display: none !important;
        }

        /* ─── Force full-screen : html/body/wrappers occupent 100vh sans marge ─── */
        html, body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
        }

        .fi-simple-layout,
        .fi-simple-page,
        .fi-simple-main-ctn,
        .fi-simple-main {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
            min-height: 100vh !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            justify-content: stretch !important;
            gap: 0 !important;
        }

        .fi-simple-main > * {
            flex: 1 !important;
            max-width: none !important;
            width: 100% !important;
        }

        /* ─── Wrapper split-screen ─── */
        .seer-login-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* ═══════════════ HERO GAUCHE ═══════════════ */
        .seer-hero {
            flex: 1.3;
            background:
                linear-gradient(rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.75)),
                linear-gradient(135deg, #059669 0%, #1e40af 50%, #7c3aed 100%);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .seer-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 30% 40%, rgba(255, 255, 255, 0.12) 0%, transparent 50%),
                conic-gradient(from 45deg at 70% 60%, rgba(124, 58, 237, 0.25), transparent 60deg);
            pointer-events: none;
        }
        .seer-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(2px 2px at 20% 30%, rgba(255,255,255,0.4), transparent),
                radial-gradient(1px 1px at 60% 70%, rgba(255,255,255,0.5), transparent),
                radial-gradient(1.5px 1.5px at 50% 20%, rgba(255,255,255,0.3), transparent),
                radial-gradient(1px 1px at 80% 40%, rgba(255,255,255,0.4), transparent),
                radial-gradient(1px 1px at 30% 80%, rgba(255,255,255,0.3), transparent);
            background-size: 500px 500px;
            pointer-events: none;
        }

        .seer-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            z-index: 2;
        }
        .seer-brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
        }
        .seer-brand-name {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .seer-hero-content {
            margin: auto 0;
            position: relative;
            z-index: 2;
        }
        .seer-title {
            font-size: clamp(2rem, 4vw, 3.25rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.02em;
            margin: 0 0 20px 0;
            color: white;
        }
        .seer-title-accent {
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .seer-tagline {
            font-size: 1.15rem;
            line-height: 1.55;
            opacity: 0.9;
            max-width: 500px;
            margin: 0 0 40px 0;
            color: rgba(255,255,255,0.9);
        }

        .seer-features {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .seer-feature {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 12px;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            color: white;
        }
        .seer-feature-icon { font-size: 1.1rem; }

        .seer-footer {
            margin-top: 40px;
            font-size: 0.8rem;
            opacity: 0.7;
            position: relative;
            z-index: 2;
        }
        .seer-footer-acronym {
            margin-top: 6px;
            font-weight: 700;
            letter-spacing: 0.15em;
            font-size: 0.75rem;
            text-transform: uppercase;
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 1;
        }

        /* ═══════════════ FORMULAIRE DROITE ═══════════════ */
        .seer-form-panel {
            flex: 1;
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .seer-form-card {
            width: 100%;
            max-width: 400px;
        }
        .seer-form-header { margin-bottom: 24px; }
        .seer-form-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 8px 0;
            letter-spacing: -0.01em;
        }
        .seer-form-sub {
            color: #6b7280;
            margin: 0;
            font-size: 0.95rem;
        }

        /* Bouton de connexion primary (dégradé SEER) */
        .seer-form-card .fi-btn-color-primary {
            background: linear-gradient(135deg, #059669 0%, #1e40af 100%) !important;
            border: none !important;
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3) !important;
            transition: transform 0.15s, box-shadow 0.15s !important;
        }
        .seer-form-card .fi-btn-color-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 12px 28px rgba(5, 150, 105, 0.4) !important;
        }

        .seer-form-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f3f4f6;
            text-align: center;
            font-size: 0.85rem;
            color: #6b7280;
        }
        .seer-form-footer a {
            color: #059669;
            font-weight: 600;
            text-decoration: none;
            margin-left: 4px;
        }
        .seer-form-footer a:hover { text-decoration: underline; }

        /* ═══════════════ MODE SOMBRE ═══════════════ */
        .dark .seer-form-panel { background: #1f2937; }
        .dark .seer-form-title { color: #f9fafb; }
        .dark .seer-form-sub { color: #9ca3af; }
        .dark .seer-form-footer { border-top-color: #374151; color: #9ca3af; }

        /* ═══════════════ RESPONSIVE ═══════════════ */
        @media (max-width: 900px) {
            .seer-login-wrapper { flex-direction: column; }
            .seer-hero { min-height: 40vh; padding: 40px 30px; }
            .seer-title { font-size: 2rem; }
            .seer-tagline { font-size: 1rem; margin-bottom: 24px; }
            .seer-form-panel { padding: 40px 30px; min-height: 60vh; }
        }
        @media (max-width: 500px) {
            .seer-hero { padding: 30px 20px; }
            .seer-form-panel { padding: 30px 20px; }
            .seer-features { flex-direction: column; align-items: flex-start; }
            .seer-feature { width: 100%; }
        }
    </style>
    @endpush
</x-filament-panels::page.simple>
