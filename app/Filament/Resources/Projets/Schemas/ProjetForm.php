<?php

namespace App\Filament\Resources\Projets\Schemas;

use App\Models\Convention;
use App\Models\Guichet;
use App\Models\Region;
use App\Models\Secteur;
use App\Models\StatutProjet;
use App\Models\Vague;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identification')->columns(2)->schema([
                TextInput::make('reference')->required()->maxLength(50)->unique(ignoreRecord: true),
                Select::make('statut_projet_id')
                    ->label('Statut')
                    ->options(StatutProjet::orderBy('ordre')->pluck('libelle', 'id'))
                    ->required()->searchable(),
                TextInput::make('intitule')->required()->maxLength(300)->columnSpanFull(),
                Select::make('convention_id')
                    ->label('Convention')
                    ->options(Convention::pluck('reference', 'id'))
                    ->searchable()->nullable(),
                Select::make('guichet_id')->label('Guichet')->options(Guichet::pluck('libelle', 'id'))->searchable(),
                Select::make('vague_id')->label('Vague')->options(Vague::pluck('libelle', 'id'))->searchable(),
                Select::make('secteur_id')->label('Secteur')->options(Secteur::pluck('libelle', 'id'))->searchable(),
                Select::make('region_id')->label('Région')->options(Region::pluck('libelle', 'id'))->searchable(),
            ]),
            Section::make('Dates')->columns(2)->schema([
                DatePicker::make('date_debut')->label('Date début'),
                DatePicker::make('date_fin')->label('Date fin'),
            ]),
            Textarea::make('observations')->rows(3)->columnSpanFull(),
        ]);
    }
}
