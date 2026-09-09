@php
    $statutCode = $getRecord()?->projet?->statut?->code ?? '';
    $statutLabel = $getRecord()?->projet?->statut?->libelle ?? '';
    $couleur = $statutCode === 'annule' ? 'red' : 'gray';
@endphp

<div class="seer-locked-banner seer-locked-{{ $couleur }}">
    <div class="seer-locked-icon">🔒</div>
    <div class="seer-locked-content">
        <div class="seer-locked-title">
            Projet verrouillé — {{ $statutLabel ?: strtoupper($statutCode) }}
        </div>
        <div class="seer-locked-desc">
            Ce projet a été @if($statutCode === 'cloture') clôturé @else annulé @endif et est en <b>lecture seule</b>.
            Seul un <b>Super Administrateur</b> peut le modifier.
            Pour toute demande, contactez : <a href="mailto:support@fmfp.mg">support@fmfp.mg</a>
        </div>
    </div>
</div>

<style>
    .seer-locked-banner {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        padding: 20px 24px;
        border-radius: 14px;
        margin-bottom: 20px;
        border-left: 6px solid;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    }
    .seer-locked-gray {
        background: linear-gradient(90deg, #f3f4f6 0%, #ffffff 100%);
        border-left-color: #6b7280;
    }
    .seer-locked-red {
        background: linear-gradient(90deg, #fef2f2 0%, #ffffff 100%);
        border-left-color: #dc2626;
    }
    .dark .seer-locked-gray {
        background: linear-gradient(90deg, rgb(31, 41, 55) 0%, rgb(17, 24, 39) 100%);
    }
    .dark .seer-locked-red {
        background: linear-gradient(90deg, rgba(127, 29, 29, 0.3) 0%, rgb(17, 24, 39) 100%);
    }
    .seer-locked-icon {
        font-size: 2.2rem;
        flex-shrink: 0;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
    }
    .seer-locked-content { flex: 1; }
    .seer-locked-title {
        font-weight: 800;
        font-size: 1.1rem;
        color: rgb(17, 24, 39);
        letter-spacing: 0.02em;
        margin-bottom: 6px;
    }
    .seer-locked-red .seer-locked-title { color: #dc2626; }
    .dark .seer-locked-title { color: #f9fafb; }
    .dark .seer-locked-red .seer-locked-title { color: #fca5a5; }
    .seer-locked-desc {
        font-size: 0.9rem;
        color: rgb(75, 85, 99);
        line-height: 1.5;
    }
    .dark .seer-locked-desc { color: rgb(156, 163, 175); }
    .seer-locked-desc a {
        color: rgb(30, 64, 175);
        font-weight: 600;
        text-decoration: none;
    }
    .seer-locked-desc a:hover { text-decoration: underline; }
    .dark .seer-locked-desc a { color: #60a5fa; }
</style>
