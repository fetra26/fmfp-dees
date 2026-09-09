<?php

namespace App\Filament\Resources\ImportConflicts\Tables;

use App\Models\ImportConflict;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ImportConflictsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('entite_type')
                    ->label('Entité')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'projet' => 'info',
                        'porteur' => 'success',
                        'porteur_proj' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('entite_id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('champ')
                    ->label('Champ')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('valeur_db')
                    ->label('Valeur en base')
                    ->wrap()
                    ->limit(60)
                    ->color('success'),

                TextColumn::make('valeur_excel')
                    ->label('Valeur Excel (rejetée)')
                    ->wrap()
                    ->limit(60)
                    ->color('warning'),

                TextColumn::make('ligne_excel')
                    ->label('Ligne')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('fichier')
                    ->label('Fichier')
                    ->limit(30)
                    ->toggleable(),

                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'en_attente' => 'warning',
                        'garde_db' => 'success',
                        'pris_excel' => 'info',
                        'ignore' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Détecté le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('resoluPar.name')
                    ->label('Résolu par')
                    ->toggleable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('statut')
                    ->options([
                        'en_attente' => 'En attente',
                        'garde_db' => 'Gardé DB',
                        'pris_excel' => 'Pris Excel',
                        'ignore' => 'Ignoré',
                    ])
                    ->default('en_attente'),

                SelectFilter::make('entite_type')
                    ->label('Entité')
                    ->options([
                        'projet' => 'Projet',
                        'porteur' => 'Porteur',
                        'porteur_proj' => 'Porteur/Projet',
                    ]),
            ])
            ->recordActions([
                Action::make('garder_db')
                    ->label('Garder DB')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ImportConflict $r) => $r->statut === 'en_attente')
                    ->requiresConfirmation()
                    ->action(function (ImportConflict $r) {
                        $r->update([
                            'statut' => 'garde_db',
                            'resolu_par' => auth()->id(),
                            'resolu_le' => now(),
                        ]);
                    }),

                Action::make('prendre_excel')
                    ->label('Prendre Excel')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn (ImportConflict $r) => $r->statut === 'en_attente')
                    ->requiresConfirmation()
                    ->modalDescription('L\'existant sera écrasé par la valeur Excel. Confirmer ?')
                    ->action(function (ImportConflict $r) {
                        // Appliquer la valeur Excel dans l'entité concernée
                        $model = match ($r->entite_type) {
                            'projet' => \App\Models\Projet::find($r->entite_id),
                            'porteur' => \App\Models\Porteur::find($r->entite_id),
                            'porteur_proj' => \App\Models\PorteurProj::find($r->entite_id),
                            default => null,
                        };
                        if ($model && $r->champ) {
                            $model->{$r->champ} = $r->valeur_excel;
                            $model->save();
                        }
                        $r->update([
                            'statut' => 'pris_excel',
                            'resolu_par' => auth()->id(),
                            'resolu_le' => now(),
                        ]);
                    }),

                Action::make('ignorer')
                    ->label('Ignorer')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (ImportConflict $r) => $r->statut === 'en_attente')
                    ->action(function (ImportConflict $r) {
                        $r->update([
                            'statut' => 'ignore',
                            'resolu_par' => auth()->id(),
                            'resolu_le' => now(),
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
