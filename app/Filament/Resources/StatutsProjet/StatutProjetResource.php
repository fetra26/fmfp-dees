<?php

namespace App\Filament\Resources\StatutsProjet;

use App\Filament\Resources\StatutsProjet\Pages\CreateStatutProjet;
use App\Filament\Resources\StatutsProjet\Pages\EditStatutProjet;
use App\Filament\Resources\StatutsProjet\Pages\ListStatutsProjet;
use App\Models\StatutProjet;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatutProjetResource extends Resource
{
    protected static ?string $model = StatutProjet::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Référentiels';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Statut projet';
    protected static ?string $pluralModelLabel = 'Statuts projet';
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFlag;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Statut de projet')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('code')
                            ->label('Code (technique)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Ex: stand_by, formation_encours (utilisé par l\'import)'),

                        TextInput::make('libelle')
                            ->label('Libellé affiché')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Ex: "STAND BY", "Formation en cours"'),

                        TextInput::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0)
                            ->helperText('Petit nombre = affiché en premier'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ordre')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('code')
                    ->label('Code technique')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('projets_count')
                    ->label('Nb projets')
                    ->counts('projets')
                    ->badge()
                    ->color(fn (int $state) => $state === 0 ? 'gray' : 'success')
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (StatutProjet $record) {
                        if ($record->projets()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Suppression impossible')
                                ->body("Ce statut est utilisé par {$record->projets()->count()} projet(s). Réaffectez-les avant de supprimer.")
                                ->danger()
                                ->send();
                            $this->halt();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('ordre');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListStatutsProjet::route('/'),
            'create' => CreateStatutProjet::route('/create'),
            'edit'   => EditStatutProjet::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }
}
