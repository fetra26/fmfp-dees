<?php

namespace App\Filament\Resources\Entreprises\Schemas;

use App\Models\Region;
use App\Models\Secteur;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EntrepriseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identification')->columns(2)->schema([
                TextInput::make('raison_sociale')->required()->maxLength(200)->columnSpanFull(),
                TextInput::make('sigle')->maxLength(50),
                TextInput::make('forme_juridique')->maxLength(50),
                TextInput::make('nif')->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('cnaps')->maxLength(30),
            ]),
            Section::make('Localisation & Secteur')->columns(2)->schema([
                Select::make('secteur_id')->label('Secteur')->options(Secteur::pluck('libelle', 'id'))->searchable(),
                Select::make('region_id')->label('Région')->options(Region::pluck('libelle', 'id'))->searchable(),
                TextInput::make('adresse')->maxLength(300)->columnSpanFull(),
                TextInput::make('ville')->maxLength(100),
            ]),
            Section::make('Contact')->columns(2)->schema([
                TextInput::make('telephone')->maxLength(50),
                TextInput::make('email')->email()->maxLength(150),
                TextInput::make('responsable_nom')->maxLength(150),
                TextInput::make('responsable_fonction')->maxLength(100),
            ]),
            Textarea::make('notes')->rows(3)->columnSpanFull(),
        ]);
    }
}
