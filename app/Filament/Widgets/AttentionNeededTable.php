<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Assets whose warranty is about to run out — the easiest thing to miss and
 * the most expensive to notice late.
 */
class AttentionNeededTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Warranties Expiring Soon')
            ->description('Assets whose warranty ends within the next 90 days.')
            ->emptyStateHeading('No warranties are expiring soon')
            ->query(fn (): Builder => Asset::query()
                ->active()
                ->warrantyExpiringWithin(90)
                ->with(['category', 'currentEmployee', 'currentLocation', 'branch'])
                ->orderBy('warranty_end'))
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('name')
                    ->label('Asset Name')
                    ->wrap(),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextColumn::make('current_holder')
                    ->label('Position'),
                TextColumn::make('warranty_end')
                    ->label('Expires')
                    ->date('d M Y')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('warranty_vendor')
                    ->label('Vendor')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Asset $record): string => AssetResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([5, 10, 25]);
    }
}
