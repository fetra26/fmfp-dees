<?php

namespace App\Filament\Resources\Paiements;

use App\Filament\Resources\Paiements\Pages\CreatePaiement;
use App\Filament\Resources\Paiements\Pages\EditPaiement;
use App\Filament\Resources\Paiements\Pages\ListPaiements;
use App\Filament\Resources\Paiements\Schemas\PaiementForm;
use App\Filament\Resources\Paiements\Tables\PaiementsTable;
use App\Models\Paiement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * Ressource "Paiements" — équipe DAF principalement.
 *
 * ─ ACCÈS ─
 *   • DAF / admin              → CRUD complet
 *   • DEES / évaluateur / DG   → LECTURE SEULE (pas de bouton Créer/Éditer/Supprimer)
 *   • Autres                    → menu invisible
 */
class PaiementResource extends Resource
{
    protected static ?string $model = Paiement::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Financier';
    protected static ?string $modelLabel = 'Paiement';
    protected static ?string $pluralModelLabel = 'Paiements';
    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function form(Schema $schema): Schema
    {
        return PaiementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaiementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPaiements::route('/'),
            'create' => CreatePaiement::route('/create'),
            'edit'   => EditPaiement::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'porteurProj:id,projet_id,porteur_id',
                'porteurProj.projet:id,reference,intitule',
                'porteurProj.porteur:id,raison_sociale',
                'creePar:id,name',
            ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // ═══ CONTRÔLE D'ACCÈS ═══

    /** Menu visible pour tous les rôles internes (lecture pour les non-DAF) */
    public static function canViewAny(): bool
    {
        return Auth::user()?->peutVoirPaiement() ?? false;
    }

    /** Créer/Éditer/Supprimer : DAF ou admin uniquement */
    public static function canCreate(): bool { return self::peutEditer(); }
    public static function canEdit($record): bool { return self::peutEditer(); }
    public static function canDelete($record): bool { return self::peutEditer(); }

    public static function peutEditer(): bool
    {
        return Auth::user()?->peutEditerPaiement() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }
}
