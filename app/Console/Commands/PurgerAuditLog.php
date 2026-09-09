<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Purge les entrées d'audit trop anciennes pour préserver les performances.
 * À planifier dans routes/console.php pour un run mensuel/trimestriel.
 */
class PurgerAuditLog extends Command
{
    protected $signature = 'audit:purger
                            {--jours=365 : Supprimer les événements plus vieux que N jours (défaut: 1 an)}
                            {--dry-run : Simuler sans supprimer}';

    protected $description = 'Purge les entrées anciennes du journal d\'audit';

    public function handle(): int
    {
        $jours = (int) $this->option('jours');
        $seuil = now()->subDays($jours);

        $nb = Activity::where('created_at', '<', $seuil)->count();
        $total = Activity::count();

        $this->info(sprintf(
            'Journal d\'audit : %d entrées au total.',
            $total
        ));
        $this->info(sprintf(
            '%d entrées plus vieilles que %d jours (avant le %s).',
            $nb, $jours, $seuil->format('d/m/Y')
        ));

        if ($nb === 0) {
            $this->line('Aucune purge nécessaire.');
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->warn('Mode dry-run — aucune suppression effectuée.');
            return 0;
        }

        if (! $this->confirm("Supprimer {$nb} entrées ?", true)) {
            $this->line('Annulé.');
            return 0;
        }

        // Suppression par chunks pour éviter les gros SQL
        $supprimes = 0;
        Activity::where('created_at', '<', $seuil)
            ->chunkById(1000, function ($rows) use (&$supprimes) {
                $ids = $rows->pluck('id')->all();
                DB::table('activity_log')->whereIn('id', $ids)->delete();
                $supprimes += count($ids);
            });

        $this->info("✔ {$supprimes} entrées supprimées.");
        $this->info("Reste : " . Activity::count() . " entrées.");

        return 0;
    }
}
