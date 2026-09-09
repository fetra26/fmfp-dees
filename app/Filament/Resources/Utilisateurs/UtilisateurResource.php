<?php

namespace App\Filament\Resources\Utilisateurs;

use App\Filament\Resources\Utilisateurs\Pages\CreateUtilisateur;
use App\Filament\Resources\Utilisateurs\Pages\EditUtilisateur;
use App\Filament\Resources\Utilisateurs\Pages\ListUtilisateurs;
use App\Models\Departement;
use App\Models\User;
use App\Services\DashboardWidgetCatalog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UtilisateurResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Administration';
    protected static ?string $modelLabel = 'Utilisateur';
    protected static ?string $pluralModelLabel = 'Utilisateurs';
    protected static ?int $navigationSort = 1;
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('utilisateur_tabs')
                ->columnSpanFull()
                ->tabs([

                    // ─── Onglet 1 : Identité ───
                    Tab::make('👤 Identité')
                        ->schema([
                            Section::make('Identité de l\'utilisateur')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('matricule')
                                            ->label('Matricule')
                                            ->maxLength(50)
                                            ->helperText('Auto-généré selon le département sélectionné (ex: DEES01, DAF03). Modifiable manuellement.')
                                            ->extraInputAttributes(['style' => 'font-family: monospace; text-transform: uppercase;']),

                                        TextInput::make('name')
                                            ->label('Nom d\'affichage')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('first_name')
                                            ->label('Prénom')
                                            ->maxLength(100),

                                        TextInput::make('last_name')
                                            ->label('Nom de famille')
                                            ->maxLength(100),

                                        TextInput::make('email')
                                            ->label('Email professionnel')
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true),

                                        TextInput::make('phone')
                                            ->label('Téléphone')
                                            ->tel()
                                            ->maxLength(30),
                                    ]),
                                ]),

                            Section::make('Sécurité')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('password')
                                            ->label('Mot de passe')
                                            ->password()
                                            ->revealable()
                                            ->required(fn (string $context) => $context === 'create')
                                            ->minLength(8)
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                            ->helperText('Laisser vide en édition pour ne pas changer'),

                                        TextInput::make('password_confirmation')
                                            ->label('Confirmer le mot de passe')
                                            ->password()
                                            ->revealable()
                                            ->required(fn (string $context, callable $get) =>
                                                $context === 'create' || filled($get('password')))
                                            ->same('password')
                                            ->dehydrated(false),
                                    ]),

                                    Toggle::make('is_active')
                                        ->label('Compte actif')
                                        ->default(true)
                                        ->helperText('Décocher pour désactiver le compte sans le supprimer')
                                        ->inline(false),
                                ]),
                        ]),

                    // ─── Onglet 2 : Département & Rôle ───
                    Tab::make('🏢 Département & Rôle')
                        ->schema([
                            Section::make('Rattachement organisationnel')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('departement_id')
                                            ->label('Département')
                                            ->relationship('departement', 'nom', fn ($query) => $query->where('is_active', true))
                                            ->getOptionLabelFromRecordUsing(fn (Departement $d) => "{$d->code} — {$d->nom}")
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Aucun département')
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get, string $context) {
                                                // Auto-fill matricule uniquement en création (pas d'écrasement en édition)
                                                if ($context !== 'create') return;
                                                if (blank($state)) return;
                                                // Ne pas écraser si l'utilisateur a déjà saisi manuellement
                                                if (! blank($get('matricule'))) return;

                                                $matricule = User::genererMatricule((int) $state);
                                                if ($matricule) {
                                                    $set('matricule', $matricule);
                                                }
                                            }),

                                        Select::make('organization_type')
                                            ->label('Type d\'organisation')
                                            ->options([
                                                'internal' => 'Interne FMFP-DEES',
                                                'external' => 'Externe (évaluateur, prestataire...)',
                                            ])
                                            ->default('internal')
                                            ->live(),
                                    ]),

                                    TextInput::make('external_organization')
                                        ->label('Nom de l\'organisation externe')
                                        ->maxLength(255)
                                        ->visible(fn (callable $get) => $get('organization_type') === 'external')
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Rôles Spatie')
                                ->description('Chaque rôle donne un ensemble de permissions défini dans config/roles.php')
                                ->schema([
                                    CheckboxList::make('roles')
                                        ->label('Rôles assignés')
                                        ->relationship('roles', 'name')
                                        ->getOptionLabelFromRecordUsing(fn (Role $role) =>
                                            self::libelleRole($role->name) . " — " . $role->name
                                        )
                                        ->searchable()
                                        ->bulkToggleable()
                                        ->columns(2)
                                        ->helperText('Un utilisateur peut cumuler plusieurs rôles'),
                                ]),
                        ]),

                    // ─── Onglet 3 : Permissions directes (optionnel) ───
                    Tab::make('🔐 Permissions directes')
                        ->badge('avancé')
                        ->badgeColor('warning')
                        ->schema([
                            Section::make('Permissions supplémentaires')
                                ->description('Permissions accordées EN PLUS des rôles ci-dessus (usage rare — préférer modifier le rôle)')
                                ->collapsed()
                                ->schema([
                                    CheckboxList::make('permissions')
                                        ->label('Permissions directes')
                                        ->relationship('permissions', 'name')
                                        ->getOptionLabelFromRecordUsing(fn (Permission $p) => $p->name)
                                        ->searchable()
                                        ->columns(3)
                                        ->bulkToggleable(),
                                ]),
                        ]),

                    // ─── Onglet 4 : Widgets tableau de bord (override individuel) ───
                    Tab::make('📊 Widgets tableau de bord')
                        ->badge(fn (?User $record) => is_array($record?->dashboard_widgets_override)
                            ? 'override'
                            : 'hérité')
                        ->badgeColor(fn (?User $record) => is_array($record?->dashboard_widgets_override)
                            ? 'warning'
                            : 'gray')
                        ->schema([
                            Section::make('Override individuel')
                                ->description(fn (?User $record): string =>
                                    $record?->departement
                                        ? "Par défaut, cet utilisateur hérite du profil du département **{$record->departement->code}**. "
                                          . 'Activez le toggle ci-dessous pour lui définir une liste personnalisée (cas exceptionnel).'
                                        : 'Cet utilisateur n\'a pas de département assigné — il verra les widgets ci-dessous, ou tous si rien n\'est coché.'
                                )
                                ->schema([
                                    Toggle::make('has_widgets_override')
                                        ->label('Utiliser une configuration personnalisée (override)')
                                        ->helperText('Si désactivé : hérite du profil du département. Si activé : utilise les cases cochées ci-dessous.')
                                        ->live()
                                        ->dehydrated(false)
                                        ->afterStateHydrated(function (Toggle $component, ?User $record): void {
                                            $component->state(is_array($record?->dashboard_widgets_override));
                                        }),

                                    CheckboxList::make('dashboard_widgets_override')
                                        ->label('Widgets à afficher')
                                        ->options(DashboardWidgetCatalog::optionsPourFormulaire())
                                        ->bulkToggleable()
                                        ->searchable()
                                        ->columns(2)
                                        ->visible(fn (callable $get) => (bool) $get('has_widgets_override'))
                                        ->dehydrateStateUsing(fn ($state, callable $get) =>
                                            $get('has_widgets_override') ? $state : null
                                        ),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Utilisateur')
                    ->description(fn (User $r) => $r->email)
                    ->searchable(['name', 'first_name', 'last_name', 'email'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('matricule')
                    ->label('Matricule')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('departement.code')
                    ->label('Département')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->sortable()
                    ->tooltip(fn (User $r) => $r->departement?->nom),

                TextColumn::make('roles.name')
                    ->label('Rôles')
                    ->badge()
                    ->separator(',')
                    ->color(fn (string $state) => match ($state) {
                        'admin'            => 'danger',
                        'project_manager'  => 'primary',
                        'daf'              => 'success',
                        'direction'        => 'info',
                        'evaluateur'       => 'warning',
                        default            => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => self::libelleRole($state)),

                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('last_login_at')
                    ->label('Dernière connexion')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Jamais')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('departement_id')
                    ->label('Département')
                    ->relationship('departement', 'nom')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('roles')
                    ->label('Rôle')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Statut')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Désactivés'),
            ])
            ->recordActions([
                Action::make('reset_password')
                    ->label('Réinitialiser MDP')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Réinitialiser le mot de passe')
                    ->modalDescription(fn (User $r) => "Un nouveau mot de passe temporaire sera généré pour {$r->name}. Communiquez-le en main propre.")
                    ->action(function (User $r): void {
                        $nouveau = 'Fmfp' . random_int(10000, 99999) . '!';
                        $r->update(['password' => Hash::make($nouveau)]);
                        Notification::make()
                            ->title('Mot de passe réinitialisé')
                            ->body("Nouveau mot de passe : {$nouveau}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListUtilisateurs::route('/'),
            'create' => CreateUtilisateur::route('/create'),
            'edit'   => EditUtilisateur::route('/{record}/edit'),
        ];
    }

    // ═══ CONTRÔLE D'ACCÈS : Super Admin uniquement ═══
    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Traduit le nom technique du rôle en libellé lisible (depuis config/roles.php).
     */
    protected static function libelleRole(string $name): string
    {
        return config("roles.roles.{$name}.label", ucfirst($name));
    }
}
