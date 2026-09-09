<?php

namespace App\Http\Controllers;

use App\Models\PorteurProj;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Génération de PDF "Fiche projet" — 1 projet = 1 fichier PDF.
 *
 * Style : moderne, coloré, cohérent avec l'identité SEER.
 * Confidentialité : la section Paiement est masquée pour non-DAF.
 * Watermark : BROUILLON (stand_by), VERROUILLÉ (cloture), ANNULÉ (annule).
 */
class FicheProjetController extends Controller
{
    public function show(Request $request, int $porteurProjId): Response
    {
        $user = Auth::user();
        abort_if(! $user, 403);

        $porteurProj = PorteurProj::with([
            'projet.statut',
            'projet.guichet',
            'projet.vague',
            'projet.secteur',
            'projet.region',
            'porteur',
            'partenaires',
            'benefs',
            'paiements',
            'evaluateur',
        ])->findOrFail($porteurProjId);

        // Filtre les paiements : le contenu détaillé n'apparaît que pour DAF/Admin
        $peutVoirPaiement = $user->peutEditerPaiement() || $user->isDirection() || $user->isSuperAdmin();

        $data = [
            'porteurProj'      => $porteurProj,
            'peutVoirPaiement' => $peutVoirPaiement,
            'generePar'        => $user->name . ' (' . ($user->roles->pluck('name')->first() ?? 'utilisateur') . ')',
        ];

        $pdf = Pdf::loadView('exports.fiche-projet', $data)
            ->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);

        $ref = $porteurProj->projet?->reference ?: 'projet_' . $porteurProjId;
        $refClean = preg_replace('/[^A-Za-z0-9_-]/', '_', $ref);
        $filename = 'SEER_PROJET_' . $refClean . '_' . now()->format('Y-m-d') . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Version téléchargement direct (au lieu de stream inline)
     */
    public function download(Request $request, int $porteurProjId): Response
    {
        $user = Auth::user();
        abort_if(! $user, 403);

        $porteurProj = PorteurProj::with([
            'projet.statut', 'projet.guichet', 'projet.vague', 'projet.secteur', 'projet.region',
            'porteur', 'partenaires', 'benefs', 'paiements', 'evaluateur',
        ])->findOrFail($porteurProjId);

        $peutVoirPaiement = $user->peutEditerPaiement() || $user->isDirection() || $user->isSuperAdmin();

        $pdf = Pdf::loadView('exports.fiche-projet', [
            'porteurProj'      => $porteurProj,
            'peutVoirPaiement' => $peutVoirPaiement,
            'generePar'        => $user->name,
        ])->setPaper('a4', 'portrait');

        $ref = $porteurProj->projet?->reference ?: 'projet_' . $porteurProjId;
        $refClean = preg_replace('/[^A-Za-z0-9_-]/', '_', $ref);
        $filename = 'SEER_PROJET_' . $refClean . '_' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
