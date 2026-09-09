<?php

namespace App\Filament\Resources\Conventions\Schemas;

use App\Models\Projet;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConventionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('projet_id')->label('Projet')->options(Projet::pluck('reference', 'id'))->required()->searchable()->columnSpanFull(),
                TextInput::make('numero')->required()->maxLength(50)->unique(ignoreRecord: true),
                DatePicker::make('date_signature')->label('Date signature'),
                DatePicker::make('date_effet')->label('Date d\'effet'),
                DatePicker::make('date_expiration')->label('Date expiration'),
            ]),
            Section::make('Montants (Ariary)')->columns(2)->schema([
                TextInput::make('montant_j1')->label('J1')->numeric()->default(0),
                TextInput::make('montant_j2')->label('J2')->numeric()->default(0),
                TextInput::make('montant_j3')->label('J3')->numeric()->default(0),
                TextInput::make('fonds_additionnel')->label('Fonds additionnel')->numeric()->default(0),
                TextInput::make('fonds_mutualise')->label('Fonds mutualisé')->numeric()->default(0),
                TextInput::make('montant_total')->label('Total')->numeric()->default(0)->readOnly(),
            ]),
            Textarea::make('observations')->rows(3)->columnSpanFull(),
        ]);
    }
}
