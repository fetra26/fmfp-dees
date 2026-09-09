<?php

namespace App\Filament\Resources\Reserves\Schemas;

use App\Models\Projet;
use App\Models\RapportTechnique;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ReserveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('projet_id')->label('Projet')->options(Projet::pluck('reference', 'id'))->required()->searchable(),
            Select::make('rapport_technique_id')->label('Rapport technique')->options(RapportTechnique::pluck('reference', 'id'))->searchable(),
            Textarea::make('description')->required()->rows(3)->columnSpanFull(),
            Select::make('niveau')->options(['mineure' => 'Mineure', 'majeure' => 'Majeure', 'bloquante' => 'Bloquante'])->default('mineure'),
            Select::make('statut')->options(['ouverte' => 'Ouverte', 'en_cours' => 'En cours', 'levee' => 'Levée'])->default('ouverte'),
            DatePicker::make('echeance')->label('Échéance'),
            DatePicker::make('date_levee')->label('Date levée'),
            Textarea::make('reponse_porteur')->label('Réponse porteur')->rows(3)->columnSpanFull(),
        ]);
    }
}
