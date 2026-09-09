@php
    use Filament\Tables\Enums\ColumnManagerResetActionPosition;
    use Illuminate\View\ComponentAttributeBag;
@endphp

@props([
    'applyAction',
    'columns' => null,
    'hasReorderableColumns',
    'hasToggleableColumns',
    'headingTag' => 'h3',
    'reorderAnimationDuration' => 300,
    'resetActionPosition' => ColumnManagerResetActionPosition::Header,
])

<div
    x-data="filamentTableColumnManager({
                columns: $wire.entangle('tableColumns'),
                isLive: {{ $applyAction->isVisible() ? 'false' : 'true' }},
            })"
    class="fi-ta-col-manager"
>
    <div class="fi-ta-col-manager-header">
        <{{ $headingTag }} class="fi-ta-col-manager-heading">
            {{ __('filament-tables::table.column_manager.heading') }}
        </{{ $headingTag }}>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            {{-- Bouton "Tout cocher" ajouté --}}
            <x-filament::link
                tag="button"
                color="primary"
                x-on:click="
                    columns = columns.map(item => {
                        if (item.type === 'column' && item.isToggleable) {
                            item.isToggled = true;
                        }
                        if (item.type === 'group' && item.columns) {
                            item.columns = item.columns.map(col => {
                                if (col.isToggleable) col.isToggled = true;
                                return col;
                            });
                        }
                        return item;
                    });
                    $wire.applyTableColumnManager(columns);
                "
            >
                Tout cocher
            </x-filament::link>

            {{-- Bouton "Tout décocher" --}}
            <x-filament::link
                tag="button"
                color="gray"
                x-on:click="
                    columns = columns.map(item => {
                        if (item.type === 'column' && item.isToggleable) {
                            item.isToggled = false;
                        }
                        if (item.type === 'group' && item.columns) {
                            item.columns = item.columns.map(col => {
                                if (col.isToggleable) col.isToggled = false;
                                return col;
                            });
                        }
                        return item;
                    });
                    $wire.applyTableColumnManager(columns);
                "
            >
                Tout décocher
            </x-filament::link>

            {{-- Bouton "Réinitialiser" original --}}
            @if ($resetActionPosition === ColumnManagerResetActionPosition::Header)
                <x-filament::link
                    :attributes="
                        \Filament\Support\prepare_inherited_attributes(
                            new ComponentAttributeBag([
                                'color' => 'danger',
                                'tag' => 'button',
                                'wire:click' => 'resetTableColumnManager',
                                'wire:loading.remove.delay.' . config('filament.livewire_loading_delay', 'default') => '',
                                'wire:target' => 'resetTableColumnManager',
                                'x-on:click' => 'resetDeferredColumns',
                            ])
                        )
                    "
                >
                    {{ __('filament-tables::table.column_manager.actions.reset.label') }}
                </x-filament::link>
            @endif
        </div>
    </div>

    <x-filament-tables::column-manager.content
        :columns="$columns"
        :has-reorderable-columns="$hasReorderableColumns"
        :has-toggleable-columns="$hasToggleableColumns"
        :reorder-animation-duration="$reorderAnimationDuration"
    />

    @if ($applyAction->isVisible() || $resetActionPosition === ColumnManagerResetActionPosition::Footer)
        <div class="fi-ta-col-manager-actions-ctn">
            @if ($applyAction->isVisible())
                {{ $applyAction }}
            @endif

            @if ($resetActionPosition === ColumnManagerResetActionPosition::Footer)
                <x-filament::button
                    color="danger"
                    wire:click="resetTableColumnManager"
                    x-on:click="resetDeferredColumns"
                >
                    {{ __('filament-tables::table.column_manager.actions.reset.label') }}
                </x-filament::button>
            @endif
        </div>
    @endif
</div>
