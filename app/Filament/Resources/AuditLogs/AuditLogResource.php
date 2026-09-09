<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Journal d'audit : historique de toutes les modifications tracées
 * par le trait LogsActivity de Spatie.
 *
 * LECTURE SEULE — pas de création, édition ou suppression via l'UI.
 * Accès contrôlé par la permission `audit_log.view`.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Administration';
    protected static ?string $modelLabel = 'Événement';
    protected static ?string $pluralModelLabel = 'Journal d\'audit';
    protected static ?int $navigationSort = 4;
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedListBullet;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date/heure')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->description(fn (Activity $r) => $r->created_at?->diffForHumans()),

                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created'  => 'success',
                        'updated'  => 'warning',
                        'deleted'  => 'danger',
                        'restored' => 'info',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'created'  => 'Création',
                        'updated'  => 'Modification',
                        'deleted'  => 'Suppression',
                        'restored' => 'Restauration',
                        default    => $state ?? '—',
                    })
                    ->sortable(),

                TextColumn::make('log_name')
                    ->label('Domaine')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject_type')
                    ->label('Entité')
                    ->formatStateUsing(fn (?string $state) => class_basename($state ?? ''))
                    ->searchable()
                    ->description(fn (Activity $r) => "ID: {$r->subject_id}"),

                TextColumn::make('causer.name')
                    ->label('Utilisateur')
                    ->default('Système')
                    ->searchable()
                    ->description(fn (Activity $r) => $r->causer?->email),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap()
                    ->tooltip(fn (Activity $r) => $r->description),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Domaine')
                    ->options(fn () => Activity::query()
                        ->select('log_name')
                        ->distinct()
                        ->pluck('log_name', 'log_name')
                        ->filter()
                        ->toArray())
                    ->searchable(),

                SelectFilter::make('event')
                    ->label('Action')
                    ->options([
                        'created'  => 'Création',
                        'updated'  => 'Modification',
                        'deleted'  => 'Suppression',
                        'restored' => 'Restauration',
                    ]),

                SelectFilter::make('causer_id')
                    ->label('Utilisateur')
                    // La relation causer est un morphTo (polymorphique) → on ne peut pas utiliser
                    // ->relationship(). On charge les options manuellement depuis User.
                    ->options(fn () => \App\Models\User::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),

                Filter::make('created_at')
                    ->label('Période')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('depuis')
                            ->label('Depuis le')
                            ->native(false),
                        \Filament\Forms\Components\DatePicker::make('jusqu_a')
                            ->label('Jusqu\'au')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['depuis'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['jusqu_a'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label('Détails'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aucun événement')
            ->emptyStateDescription('Le journal d\'audit se remplit automatiquement à chaque modification tracée.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contexte')
                ->columns(3)
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Date/heure')
                        ->dateTime('d/m/Y H:i:s'),

                    TextEntry::make('event')
                        ->label('Action')
                        ->badge()
                        ->color(fn (?string $state) => match ($state) {
                            'created'  => 'success',
                            'updated'  => 'warning',
                            'deleted'  => 'danger',
                            'restored' => 'info',
                            default    => 'gray',
                        }),

                    TextEntry::make('log_name')
                        ->label('Domaine')
                        ->badge()
                        ->color('info'),

                    TextEntry::make('subject_type')
                        ->label('Type d\'entité')
                        ->formatStateUsing(fn (?string $state) => class_basename($state ?? '')),

                    TextEntry::make('subject_id')
                        ->label('ID entité'),

                    TextEntry::make('causer.name')
                        ->label('Auteur')
                        ->default('Système')
                        ->helperText(fn (Activity $r) => $r->causer?->email),

                    TextEntry::make('description')
                        ->label('Description')
                        ->columnSpanFull(),
                ]),

            Section::make('Valeurs avant/après')
                ->description('Uniquement pour les événements de type "Modification"')
                ->collapsible()
                ->visible(fn (Activity $r) => filled($r->properties?->get('old')) || filled($r->properties?->get('attributes')))
                ->schema([
                    Grid::make(2)->schema([
                        KeyValueEntry::make('properties.old')
                            ->label('Anciennes valeurs')
                            ->keyLabel('Champ')
                            ->valueLabel('Ancienne valeur')
                            ->getStateUsing(fn (Activity $r) => $r->properties?->get('old') ?? []),

                        KeyValueEntry::make('properties.attributes')
                            ->label('Nouvelles valeurs')
                            ->keyLabel('Champ')
                            ->valueLabel('Nouvelle valeur')
                            ->getStateUsing(fn (Activity $r) => $r->properties?->get('attributes') ?? []),
                    ]),
                ]),

            Section::make('Données brutes (JSON)')
                ->collapsed()
                ->schema([
                    TextEntry::make('properties_json')
                        ->label('Propriétés complètes')
                        ->getStateUsing(fn (Activity $r) => json_encode($r->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->formatStateUsing(fn ($state) => "<pre style='font-family: monospace; font-size: 12px; background: #f3f4f6; padding: 8px; border-radius: 4px; white-space: pre-wrap;'>{$state}</pre>")
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('causer:id,name,email');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view'  => ViewAuditLog::route('/{record}'),
        ];
    }

    // ═══ CONTRÔLE D'ACCÈS ═══
    // Autorisé pour Super Admin OU utilisateur avec permission audit_log.view
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperAdmin() || $user?->can('audit_log.view');
    }

    // Lecture seule complète : pas de création, édition, suppression
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::whereDate('created_at', today())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }
}
