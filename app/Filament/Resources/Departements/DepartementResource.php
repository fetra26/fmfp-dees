<?php

namespace App\Filament\Resources\Departements;

use App\Filament\Resources\Departements\Pages\CreateDepartement;
use App\Filament\Resources\Departements\Pages\EditDepartement;
use App\Filament\Resources\Departements\Pages\ListDepartements;
use App\Models\Departement;
use App\Services\DashboardWidgetCatalog;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DepartementResource extends Resource
{
    protected static ?string $model = Departement::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Administration';
    protected static ?string $modelLabel = 'Département';
    protected static ?string $pluralModelLabel = 'Départements';
    protected static ?int $navigationSort = 2;
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('departement_tabs')
                ->columnSpanFull()
                ->tabs([

                    // ─── Onglet 1 : Général ───
                    Tab::make('🏢 Général')
                        ->schema([
                            Section::make('Informations générales')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('nom')
                                            ->label('Nom du département')
                                            ->required()
                                            ->maxLength(100)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                if (blank($get('code'))) {
                                                    $set('code', Str::upper(Str::slug(Str::substr($state, 0, 10), '')));
                                                }
                                            }),

                                        TextInput::make('code')
                                            ->label('Code court')
                                            ->required()
                                            ->maxLength(20)
                                            ->unique(ignoreRecord: true)
                                            ->helperText('Identifiant court (ex: DEES, DAF, DG)')
                                            ->extraInputAttributes(['style' => 'text-transform: uppercase;']),
                                    ]),

                                    Textarea::make('description')
                                        ->label('Description / Mission')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Configuration')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('responsable_id')
                                            ->label('Responsable / Chef de département')
                                            ->relationship('responsable', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Aucun responsable désigné'),

                                        Toggle::make('is_active')
                                            ->label('Département actif')
                                            ->default(true)
                                            ->inline(false)
                                            ->helperText('Désactiver au lieu de supprimer pour préserver l\'historique'),
                                    ]),
                                ]),
                        ]),

                    // ─── Onglet 2 : Widgets du tableau de bord ───
                    Tab::make('📊 Widgets tableau de bord')
                        ->badge(fn ($record) => is_array($record?->dashboard_widgets)
                            ? count($record->dashboard_widgets)
                            : 'tous')
                        ->badgeColor('info')
                        ->schema([
                            Section::make('Widgets visibles pour ce département')
                                ->description('Cochez les widgets qui apparaîtront sur le tableau de bord des utilisateurs de ce département. Si aucun n\'est coché → tous les widgets s\'affichent (défaut).')
                                ->schema([
                                    CheckboxList::make('dashboard_widgets')
                                        ->label('')
                                        ->options(DashboardWidgetCatalog::optionsPourFormulaire())
                                        ->descriptions(self::descriptionsWidgets())
                                        ->bulkToggleable()
                                        ->searchable()
                                        ->columns(2)
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nom')
                    ->label('Nom du département')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('responsable.name')
                    ->label('Responsable')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Nb utilisateurs')
                    ->counts('users')
                    ->badge()
                    ->color(fn (int $state) => $state === 0 ? 'gray' : 'success')
                    ->alignCenter()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Statut')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListDepartements::route('/'),
            'create' => CreateDepartement::route('/create'),
            'edit'   => EditDepartement::route('/{record}/edit'),
        ];
    }

    // ═══ CONTRÔLE D'ACCÈS : Super Admin uniquement ═══
    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Descriptions [slug => texte] pour les cases du CheckboxList.
     */
    protected static function descriptionsWidgets(): array
    {
        $descriptions = [];
        foreach (DashboardWidgetCatalog::all() as $categorie => $widgets) {
            foreach ($widgets as $slug => $widget) {
                $descriptions[$slug] = ($widget['description'] ?? '') . ' — ' . $categorie;
            }
        }
        return $descriptions;
    }
}
