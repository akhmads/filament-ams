<?php

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Enums\RepairTicketStatus;
use App\Enums\WorkOrderStatus;
use App\Exceptions\StockException;
use App\Filament\Resources\StockDocuments\StockDocumentResource;
use App\Models\Location;
use App\Models\RepairTicket;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\StockDocumentService;
use App\Support\Quantity;
use App\Support\StockItemOptions;
use App\Support\WarehouseOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Spare parts drawn from stock for maintenance work. Registered on both work
 * orders and repair tickets. Recording parts posts a goods issue at once, so
 * stock drops while the work is under way.
 */
class SparePartsRelationManager extends RelationManager
{
    protected static string $relationship = 'sparePartLines';

    protected static ?string $title = 'Spare Parts';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Spare Parts')
            ->description('Parts leave stock as soon as they are recorded, valued at the warehouse\'s average cost.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['stockItem', 'stockDocument']))
            ->defaultSort('stock_document_lines.id')
            ->emptyStateHeading('No spare parts used')
            ->columns([
                TextColumn::make('stockDocument.number')->label('Goods Issue')->badge()->color('gray')
                    ->url(fn (StockDocumentLine $record): string => StockDocumentResource::getUrl('view', ['record' => $record->stock_document_id])),
                TextColumn::make('stockDocument.document_date')->label('Date')->date('d M Y'),
                TextColumn::make('stockItem.code')->label('Code'),
                TextColumn::make('stockItem.name')->label('Part')->wrap(),
                TextColumn::make('quantity')->label('Quantity')->alignEnd()
                    ->formatStateUsing(fn (StockDocumentLine $record): string => Quantity::format($record->quantity).' '.$record->stockItem->unit),
                TextColumn::make('posted_value')->label('Cost')->alignEnd()->money('IDR')
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
            ])
            ->headerActions([
                $this->useSparePartsAction(),
            ])
            ->paginated(false);
    }

    private function useSparePartsAction(): Action
    {
        return Action::make('useSpareParts')
            ->label('Use Spare Parts')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->modalHeading('Use Spare Parts')
            ->modalDescription('The parts leave the chosen warehouse now. If it cannot cover every part, nothing is taken.')
            ->modalSubmitActionLabel('Take from stock')
            ->visible(fn (): bool => $this->canUseSpareParts())
            ->schema([
                Select::make('location_id')
                    ->label('Warehouse')
                    ->options(fn (): array => WarehouseOptions::all())
                    ->searchable()
                    ->required()
                    ->live(),
                Repeater::make('parts')
                    ->label('Parts')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('stock_item_id')
                                ->label('Part')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => StockItemOptions::search($search))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => StockItemOptions::label($value))
                                ->required()
                                ->distinct()
                                ->live()
                                ->columnSpan(2),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->helperText(fn (Get $get): ?string => StockItemOptions::onHandHint($get('stock_item_id'), $get('../../location_id')))
                                ->numeric()
                                ->rule('decimal:0,2')
                                ->minValue(0.01)
                                ->required(),
                        ]),
                    ])
                    ->addActionLabel('Add Part')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->reorderable(false),
            ])
            ->action(function (array $data, Action $action): void {
                /** @var User $user */
                $user = Auth::user();

                /** @var WorkOrder|RepairTicket $work */
                $work = $this->getOwnerRecord();

                try {
                    app(StockDocumentService::class)->issueSpareParts(
                        work: $work,
                        warehouse: Location::query()->findOrFail($data['location_id']),
                        parts: array_values($data['parts']),
                        issuedBy: $user,
                    );
                } catch (StockException $exception) {
                    Notification::make()->title('Spare parts not recorded')->body($exception->getMessage())->danger()->persistent()->send();

                    $action->halt();
                }

                Notification::make()->title('Spare parts recorded')->success()->send();
            });
    }

    private function canUseSpareParts(): bool
    {
        $work = $this->getOwnerRecord();
        $user = Auth::user();

        $isUnderWay = match (true) {
            $work instanceof WorkOrder => $work->status === WorkOrderStatus::InProgress,
            $work instanceof RepairTicket => $work->status === RepairTicketStatus::InRepair,
            default => false,
        };

        return $isUnderWay
            && (bool) $user?->can('update', $work)
            && (bool) $user?->can('create', StockDocument::class);
    }
}
