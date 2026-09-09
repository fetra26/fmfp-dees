<?php

namespace App\Filament\Resources\SuiviTerrains\Schemas;

use App\Models\Projet;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SuiviTerrainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('projet_id')->label('Projet')->options(Projet::pluck('reference', 'id'))->required()->searchable(),
            DatePicker::make('date_visite')->label('Date de visite')->required(),
            TextInput::make('lieu')->maxLength(200),
            TextInput::make('taux_execution_observe')->label("Taux d'exécution observé (%)")->numeric()->minValue(0)->maxValue(100)->suffix('%'),
            Textarea::make('constats')->rows(4)->columnSpanFull(),
            Textarea::make('recommandations')->rows(4)->columnSpanFull(),
        ]);
    }
}
