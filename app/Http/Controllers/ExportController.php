<?php

namespace App\Http\Controllers;

use App\Models\Benef;
use App\Models\Paiement;
use App\Models\PorteurProj;
use App\Models\Porteur;
use App\Models\Projet;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportController extends Controller
{
    /**
     * Génère un rapport PDF synthétique pour la direction FMFP-DEES.
     */
    public function pdfProjets()
    {
        // ─── Bilan financier ─────────────────────────────────
        $allocationTotale = PorteurProj::sum('montant_total');
        $paiementsJ1      = Paiement::where('ligne', 'J1')->where('is_annule', false)->sum('montant');
        $paiementsJ2      = Paiement::where('ligne', 'J2')->where('is_annule', false)->sum('montant');
        $paiementsJ3      = Paiement::where('ligne', 'J3')->where('is_annule', false)->sum('montant');
        $totalVerse       = $paiementsJ1 + $paiementsJ2 + $paiementsJ3;
        $tauxConsommation = $allocationTotale > 0 ? round(($totalVerse / $allocationTotale) * 100, 1) : 0;

        // ─── Bénéficiaires ───────────────────────────────────
        $prevu = Benef::where('type', 'prevu')->selectRaw('
            SUM(total) as total, SUM(h) as h, SUM(f) as f,
            SUM(jeunes) as jeunes, SUM(fpe) as fpe, SUM(cadres) as cadres
        ')->first();

        $real = Benef::where('type', 'realise')->selectRaw('
            SUM(total) as total, SUM(h) as h, SUM(f) as f,
            SUM(jeunes) as jeunes, SUM(fpe) as fpe, SUM(cadres) as cadres
        ')->first();

        // ─── Alertes ─────────────────────────────────────────
        $alertesRouges  = PorteurProj::where('niveau_alerte', 'rouge')->count();
        $alertesOranges = PorteurProj::where('niveau_alerte', 'orange')->count();
        $alertesVertes  = PorteurProj::where('niveau_alerte', 'verte')->count();

        // ─── Statuts ─────────────────────────────────────────
        $statuts = PorteurProj::selectRaw('statut_validation, COUNT(*) as total')
            ->groupBy('statut_validation')->get();

        // ─── Répartition régionale ───────────────────────────
        $parRegion = Projet::join('region', 'projet.region_id', '=', 'region.id')
            ->selectRaw('region.libelle as region, COUNT(*) as total')
            ->groupBy('region.libelle')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // ─── Répartition sectorielle ─────────────────────────
        $parSecteur = Projet::join('secteur', 'projet.secteur_id', '=', 'secteur.id')
            ->selectRaw('secteur.libelle as secteur, COUNT(*) as total')
            ->groupBy('secteur.libelle')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // ─── Financement par guichet ─────────────────────────
        $financementGuichet = PorteurProj::join('projet', 'porteur_proj.projet_id', '=', 'projet.id')
            ->join('guichet', 'projet.guichet_id', '=', 'guichet.id')
            ->selectRaw('guichet.code as guichet, SUM(porteur_proj.montant_total) as total, COUNT(*) as nb')
            ->groupBy('guichet.code')
            ->orderByDesc('total')
            ->get();

        // ─── Compteurs généraux ──────────────────────────────
        $stats = [
            'total_projets'    => Projet::count(),
            'total_porteurs'   => Porteur::count(),
            'date_generation'  => now()->format('d/m/Y à H:i'),
        ];

        $pdf = Pdf::loadView('exports.rapport-direction', compact(
            'allocationTotale', 'totalVerse', 'tauxConsommation',
            'paiementsJ1', 'paiementsJ2', 'paiementsJ3',
            'prevu', 'real',
            'alertesRouges', 'alertesOranges', 'alertesVertes',
            'statuts', 'parRegion', 'parSecteur', 'financementGuichet',
            'stats'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('rapport-direction-fmfp-dees-' . now()->format('Y-m-d') . '.pdf');
    }
}
