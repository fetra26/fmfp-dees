<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Administration';
    protected static ?string $modelLabel = 'Rôle';
    protected static ?string $pluralModelLabel = 'Rôles & Permissions';
    protected static ?int $navigationSort = 3;
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    /** Rôles protégés : ne peuvent pas être supprimés ni renommés */
    public const ROLES_PROTEGES = ['admin'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Rôle')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('label')
                            ->label('Libellé (affiché aux utilisateurs)')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get, string $context) {
                                // En création uniquement : suggère un nom technique depuis le libellé
                                if ($context === 'create' && blank($get('name'))) {
                                    $set('name', Str::slug($state, '_'));
                                }
                            })
                            ->helperText('Ex: "Chargé de projet DEES"'),

                        TextInput::make('name')
                            ->label('Nom technique (unique)')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->regex('/^[a-z0-9_]+$/')
                            ->disabled(fn ($record) => $record && in_array($record->name, self::ROLES_PROTEGES, true))
                            ->helperText('Minuscules + underscores uniquement. Ex: "project_manager"')
                            ->extraInputAttributes(['style' => 'font-family: monospace;']),
                    ]),

                    Textarea::make('description')
                        ->label('Description / Rôle métier')
                        ->rows(2)
                        ->columnSpanFull(),

                    TextInput::make('guard_name')
                        ->label('Guard')
                        ->default('web')
                        ->disabled()
                        ->helperText('Toujours "web" pour l\'interface admin'),
                ]),

            Section::make('Permissions attachées')
                ->description('Cocher/décocher les permissions de ce rôle. Prend effet immédiatement après enregistrement.')
                ->schema([
                    CheckboxList::make('permissions')
                        ->relationship('permissions', 'name')
                        ->options(fn () => Permission::orderBy('name')->pluck('name', 'id')->toArray())
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom technique')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'admin'            => 'danger',
                        'project_manager'  => 'primary',
                        'daf'              => 'success',
                        'direction'        => 'info',
                        'evaluateur'       => 'warning',
                        default            => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('label')
                    ->label('Libellé')
                    ->weight('bold')
                    ->placeholder(fn (Role $r) => config("roles.roles.{$r->name}.label", ucfirst($r->name))),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(80)
                    ->wrap()
                    ->placeholder(fn (Role $r) => config("roles.roles.{$r->name}.description", '—')),

                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),

                TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users')
                    ->badge()
                    ->color(fn (int $state) => $state === 0 ? 'gray' : 'info')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Role $r) => ! in_array($r->name, self::ROLES_PROTEGES, true))
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer ce rôle ?')
                    ->modalDescription(fn (Role $r) => "Le rôle « {$r->name} » sera supprimé. Les utilisateurs conservent leur compte mais perdent ce rôle. Confirmer ?")
                    ->before(function (Role $r) {
                        // Détacher tous les utilisateurs avant suppression pour éviter les données orphelines
                        $r->users()->detach();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Filtrer les rôles protégés
                            foreach ($records as $r) {
                                if (! in_array($r->name, self::ROLES_PROTEGES, true)) {
                                    $r->users()->detach();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit'   => EditRole::route('/{record}/edit'),
        ];
    }

    // ═══ CONTRÔLE D'ACCÈS : Super Admin uniquement ═══
    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() && ! in_array($record->name, self::ROLES_PROTEGES, true);
    }
}
