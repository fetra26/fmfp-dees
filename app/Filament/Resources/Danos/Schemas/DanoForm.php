<?php

namespace App\Filament\Resources\Danos\Schemas;

use App\Models\Projet;
use App\Models\TypeDano;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DanoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('projet_id')->label('Projet')->options(Projet::pluck('reference', 'id'))->required()->searchable(),
            Select::make('type_dano_id')->label('Type')->options(TypeDano::pluck('libelle', 'id'))->required(),
            TextInput::make('reference')->maxLength(100),
            DatePicker::make('date_dano')->label('Date DANO'),
            Textarea::make('contenu')->rows(4)->columnSpanFull(),
        ]);
    }
}
