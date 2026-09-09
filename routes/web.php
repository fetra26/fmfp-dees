<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\FicheProjetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Export PDF (rapport pour la direction) — auth Filament requise
Route::middleware(['auth'])->group(function () {
    Route::get('/export/pdf-projets', [ExportController::class, 'pdfProjets'])
        ->name('export.pdf-projets');

    // Fiche projet PDF (1 projet = 1 PDF)
    Route::get('/export/fiche-projet/{porteurProj}', [FicheProjetController::class, 'show'])
        ->name('export.fiche-projet');
    Route::get('/export/fiche-projet/{porteurProj}/download', [FicheProjetController::class, 'download'])
        ->name('export.fiche-projet.download');
});
