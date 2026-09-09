<?php

namespace App\Filament\Resources\Relances\Schemas;

use App\Models\Projet;
use App\Models\TypeRelance;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RelanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('projet_id')->label('Projet')->options(Projet::pluck('reference', 'id'))->required()->searchable(),
            Select::make('type_relance_id')->label('Type')->options(TypeRelance::pluck('libelle', 'id'))->required(),
            DatePicker::make('date_relance')->label('Date relance')->required(),
            Textarea::make('contenu')->rows(4)->columnSpanFull(),
            Toggle::make('is_repondu')->label('Répondu')->inline(false),
            DatePicker::make('date_reponse')->label('Date réponse'),
            Textarea::make('reponse')->rows(3)->columnSpanFull(),
        ]);
    }
}
