<?php

namespace App\Filament\Resources\MaintenancePlans\Tables;

use App\Enums\MaintenanceIntervalUnit;
use App\Models\MaintenancePlan;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MaintenancePlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->emptyStateHeading('No maintenance plans yet')
            ->emptyStateDescription('A plan opens work orders on a schedule for one asset or a whole category.')
            ->columns([
                TextColumn::make('name')
                    ->label('Plan')
                    ->description(fn (MaintenancePlan $record): string => $record->schedule_label)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('target')
                    ->label('Applies To')
                    ->state(fn (MaintenancePlan $record): string => self::targetLabel($record))
                    ->wrap(),
                TextColumn::make('start_date')
                    ->label('First Due')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('lead_days')
                    ->label('Lead Time')
                    ->suffix(' days')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('assignedTo.name')
                    ->label('Technician')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('open_work_orders_count')
                    ->label('Open Work Orders')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                SelectFilter::make('interval_unit')->label('Interval')->options(MaintenanceIntervalUnit::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function targetLabel(MaintenancePlan $plan): string
    {
        if ($plan->isForCategory()) {
            $suffix = $plan->include_subcategories ? ' and subcategories' : '';

            return 'Category '.($plan->category?->name ?? '—').$suffix;
        }

        return $plan->asset === null ? '—' : "{$plan->asset->code} — {$plan->asset->name}";
    }
}
