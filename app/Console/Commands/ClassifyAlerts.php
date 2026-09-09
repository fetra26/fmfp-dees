<?php

namespace App\Console\Commands;

use App\Jobs\ClassifyAlertsJob;
use Illuminate\Console\Command;

class ClassifyAlerts extends Command
{
    protected $signature = 'alertes:classifier {--sync : Exécuter immédiatement (sans queue)}';
    protected $description = 'Classifie tous les projets selon le niveau d\'alerte (vert/orange/rouge) selon les jours écoulés depuis la date de fin de convention';

    public function handle(): int
    {
        $this->info('🔔 Classification automatique des alertes en cours...');
        $this->newLine();

        $job = new ClassifyAlertsJob();

        if ($this->option('sync')) {
            $job->handle();
        } else {
            $job->handle(); // Pour l'instant on exécute toujours en sync (pas de worker queue)
        }

        $this->info('✔ Terminé.');
        $this->newLine();

        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Projets traités',          $job->totalTraites],
                ['Reclassifiés',             $job->totalReclassifies],
                ['Nouveaux verts (30-59j)',  $job->nouveauxVerte],
                ['Nouveaux oranges (60-89j)',$job->nouveauxOrange],
                ['Nouveaux rouges (90j+)',   $job->nouveauxRouge],
                ['Relances créées',          $job->relancesCreees],
            ]
        );

        return 0;
    }
}
