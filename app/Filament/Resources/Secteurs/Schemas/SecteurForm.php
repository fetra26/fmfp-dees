<?php

namespace App\Filament\Resources\Secteurs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SecteurForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('libelle')->required()->maxLength(150),
        ]);
    }
}
