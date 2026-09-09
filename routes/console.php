<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Job quotidien : classification automatique des alertes (vert/orange/rouge)
// Exécuté chaque jour à 02h00 du matin.
Schedule::command('alertes:classifier')
    ->dailyAt('02:00')
    ->onSuccess(fn () => info('ClassifyAlerts : exécuté avec succès'))
    ->onFailure(fn () => report(new \Exception('ClassifyAlerts : échec')));
