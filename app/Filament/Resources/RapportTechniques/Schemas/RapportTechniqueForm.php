<?php

namespace App\Filament\Resources\RapportTechniques\Schemas;

use App\Models\Projet;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RapportTechniqueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('projet_id')->label('Projet')->options(Projet::pluck('reference', 'id'))->required()->searchable()->columnSpanFull(),
                TextInput::make('reference')->maxLength(100),
                DatePicker::make('date_rapport')->label('Date rapport')->required(),
                Select::make('type_rapport')->options(['mi_parcours' => 'Mi-parcours', 'final' => 'Final', 'complementaire' => 'Complémentaire'])->default('final'),
                Select::make('statut')->options(['brouillon' => 'Brouillon', 'soumis' => 'Soumis', 'valide' => 'Validé', 'rejete' => 'Rejeté'])->default('brouillon'),
                Select::make('evaluateur_id')->label('Évaluateur')
                    ->options(fn () => rescue(fn () => User::role('evaluateur')->pluck('name', 'id'), User::pluck('name', 'id'), report: false))
                    ->searchable(),
                DatePicker::make('date_validation')->label('Date validation'),
            ]),
            Textarea::make('synthese')->label('Synthèse')->rows(5)->columnSpanFull(),
            Textarea::make('conclusions')->rows(4)->columnSpanFull(),
        ]);
    }
}
