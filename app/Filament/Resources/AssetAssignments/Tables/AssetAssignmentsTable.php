<?php

namespace App\Filament\Resources\AssetAssignments\Tables;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Filament\Actions\CompleteAssignmentAction;
use App\Filament\Actions\PrintHandoverAction;
use App\Models\AssetAssignment;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('assignment_date', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Number')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('assignment_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('recipient_name')
                    ->label('Received By')
                    ->description(fn (AssetAssignment $record): ?string => $record->toDepartment?->name)
                    ->searchable(false),
                TextColumn::make('items_count')
                    ->label('Asset Count')
                    ->badge()
                    ->color('info'),
                TextColumn::make('expected_return_date')
                    ->label('Due Back')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->color(fn (AssetAssignment $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(AssignmentType::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AssignmentStatus::class),
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->preload(),
                Filter::make('overdue')
                    ->label('Overdue for return')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    CompleteAssignmentAction::make(),
                    PrintHandoverAction::make(),
                    EditAction::make()
                        ->visible(fn (AssetAssignment $record): bool => $record->isEditable()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
