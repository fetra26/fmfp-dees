<?php

namespace App\Filament\Resources\Projets\RelationManagers;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RapportsTechniquesRelationManager extends RelationManager
{
    protected static string $relationship = 'rapportsTechniques';
    protected static ?string $title = 'Rapports techniques';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reference')->maxLength(100),
            DatePicker::make('date_rapport')->label('Date rapport')->required(),
            Select::make('type_rapport')->options(['mi_parcours' => 'Mi-parcours', 'final' => 'Final', 'complementaire' => 'Complémentaire'])->default('final'),
            Select::make('statut')->options(['brouillon' => 'Brouillon', 'soumis' => 'Soumis', 'valide' => 'Validé', 'rejete' => 'Rejeté'])->default('brouillon'),
            Select::make('evaluateur_id')->label('Évaluateur')
                ->options(fn () => rescue(fn () => User::role('evaluateur')->pluck('name', 'id'), User::pluck('name', 'id'), report: false))
                ->searchable(),
            DatePicker::make('date_validation')->label('Date validation'),
            Textarea::make('synthese')->rows(4)->columnSpanFull(),
            Textarea::make('conclusions')->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('reference'),
                TextColumn::make('date_rapport')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('type_rapport')->label('Type')->badge(),
                TextColumn::make('statut')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'valide' => 'success', 'rejete' => 'danger',
                        'soumis' => 'warning', default => 'gray',
                    }),
                TextColumn::make('evaluateur.name')->label('Évaluateur'),
            ])
            ->defaultSort('date_rapport', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
