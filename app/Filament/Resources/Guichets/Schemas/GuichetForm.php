<?php

namespace App\Filament\Resources\Guichets\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GuichetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true),
                TextInput::make('libelle')->required()->maxLength(150)->columnSpan(1),
                TextInput::make('region')->maxLength(100),
                Toggle::make('is_active')->default(true)->inline(false),
            ]),
        ]);
    }
}
