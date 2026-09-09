<?php

namespace App\Filament\Resources\Vagues\Schemas;

use App\Models\Guichet;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VagueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('libelle')->required()->maxLength(150),
            TextInput::make('annee')->numeric()->minValue(2000)->maxValue(2100),
            Select::make('guichet_id')->label('Guichet')->options(Guichet::pluck('libelle', 'id'))->searchable(),
            Toggle::make('is_active')->default(true)->inline(false),
        ]);
    }
}
