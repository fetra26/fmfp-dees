<?php

namespace App\Filament\Resources\Conventions;

use App\Filament\Resources\Conventions\Pages\CreateConvention;
use App\Filament\Resources\Conventions\Pages\EditConvention;
use App\Filament\Resources\Conventions\Pages\ListConventions;
use App\Filament\Resources\Conventions\Schemas\ConventionForm;
use App\Filament\Resources\Conventions\Tables\ConventionsTable;
use App\Models\Convention;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ConventionResource extends Resource
{
    protected static ?string $model = Convention::class;
    protected static string | NITENUM | NULL $NAVIGATIONGROUP = 'Cœur métier';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $modelLabel = 'Convention';
    protected static ?string $pluralModelLabel = 'Conventions';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return ConventionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConventionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConventions::route('/'),
            'create' => CreateConvention::route('/create'),
            'edit' => EditConvention::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
