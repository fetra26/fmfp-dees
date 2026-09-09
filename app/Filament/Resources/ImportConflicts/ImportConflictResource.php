<?php

namespace App\Filament\Resources\ImportConflicts;

use App\Filament\Resources\ImportConflicts\Pages\ListImportConflicts;
use App\Filament\Resources\ImportConflicts\Tables\ImportConflictsTable;
use App\Models\ImportConflict;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImportConflictResource extends Resource
{
    protected static ?string $model = ImportConflict::class;
    protected static string|\UnitEnum|null $navigationGroup = 'Imports';
    protected static ?int $navigationSort = 90;
    protected static ?string $modelLabel = 'Conflit d\'import';
    protected static ?string $pluralModelLabel = 'Conflits d\'import';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getModel()::where('statut', 'en_attente')->count();
        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('statut', 'en_attente')->count() > 0 ? 'warning' : null;
    }

    public static function table(Table $table): Table
    {
        return ImportConflictsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportConflicts::route('/'),
        ];
    }
}
