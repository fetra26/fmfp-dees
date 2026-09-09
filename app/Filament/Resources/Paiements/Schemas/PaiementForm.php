<?php

namespace App\Filament\Resources\Paiements\Schemas;

use App\Filament\Resources\Paiements\PaiementResource;
use App\Models\PorteurProj;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaiementForm
{
    public static function configure(Schema $schema): Schema
    {
        $peutEditer = PaiementResource::peutEditer();

        return $schema->components([

            Section::make('Projet concerné')
                ->description($peutEditer
                    ? 'Sélectionnez le projet auquel ce paiement se rattache.'
                    : '🔒 Section réservée à l\'équipe DAF — vous êtes en lecture seule.'
                )
                ->schema([
                    Select::make('porteur_proj_id')
                        ->label('Projet / Porteur')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->disabled(! $peutEditer)
                        ->getSearchResultsUsing(fn (string $search): array =>
                            PorteurProj::query()
                                ->with(['projet:id,reference,intitule', 'porteur:id,raison_sociale'])
                                ->whereHas('projet', fn ($q) => $q->where('reference', 'like', "%{$search}%")->orWhere('intitule', 'like', "%{$search}%"))
                                ->orWhereHas('porteur', fn ($q) => $q->where('raison_sociale', 'like', "%{$search}%"))
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn ($pp) => [
                                    $pp->id => ($pp->projet?->reference ?? 'sans réf') . ' — ' . ($pp->porteur?->raison_sociale ?? '—'),
                                ])
                                ->toArray()
                        )
                        ->getOptionLabelUsing(function ($value): ?string {
                            $pp = PorteurProj::with(['projet:id,reference', 'porteur:id,raison_sociale'])->find($value);
                            if (! $pp) return null;
                            return ($pp->projet?->reference ?? 'sans réf') . ' — ' . ($pp->porteur?->raison_sociale ?? '—');
                        })
                        ->columnSpanFull(),
                ]),

            Section::make('Détails du paiement')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('ligne')
                            ->label('Tranche')
                            ->options(['J1' => 'J1 — 1ère tranche', 'J2' => 'J2 — 2ème tranche', 'J3' => 'J3 — 3ème tranche'])
                            ->required()
                            ->disabled(! $peutEditer),

                        DatePicker::make('date_paiement')
                            ->label('Date de paiement')
                            ->native(false)
                            ->required()
                            ->disabled(! $peutEditer),

                        TextInput::make('montant')
                            ->label('Montant')
                            ->numeric()
                            ->required()
                            ->suffix('Ar')
                            ->disabled(! $peutEditer),
                    ]),

                    TextInput::make('reference_ordre')
                        ->label('Référence ordre de paiement')
                        ->maxLength(100)
                        ->disabled(! $peutEditer),
                ]),

            Section::make('Annulation')
                ->description('À remplir uniquement si le paiement a été annulé')
                ->collapsed()
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('is_annule')
                            ->label('Paiement annulé')
                            ->inline(false)
                            ->live()
                            ->disabled(! $peutEditer),

                        DatePicker::make('date_annulation')
                            ->label('Date d\'annulation')
                            ->native(false)
                            ->visible(fn (callable $get) => (bool) $get('is_annule'))
                            ->disabled(! $peutEditer),
                    ]),

                    Textarea::make('motif_annulation')
                        ->label('Motif de l\'annulation')
                        ->rows(2)
                        ->visible(fn (callable $get) => (bool) $get('is_annule'))
                        ->disabled(! $peutEditer),
                ]),

            Section::make('Observations')
                ->collapsed()
                ->schema([
                    Textarea::make('observations')
                        ->label('Notes / observations')
                        ->rows(3)
                        ->disabled(! $peutEditer),
                ]),
        ]);
    }
}
