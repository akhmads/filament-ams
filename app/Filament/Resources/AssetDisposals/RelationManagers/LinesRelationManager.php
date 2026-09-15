<?php

namespace App\Filament\Resources\AssetDisposals\RelationManagers;

use App\Enums\AssetDisposalStatus;
use App\Exceptions\AssetDisposalException;
use App\Filament\Actions\CompleteAssetDisposalAction;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\AssetDisposal;
use App\Models\AssetDisposalLine;
use App\Services\DisposalValuation;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/**
 * The assets on a disposal. Before completion the book value is an estimate from
 * the depreciation posted so far; completion fixes it with the gain or loss.
 */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Assets';

    public function isReadOnly(): bool
    {
        return true;
    }

    #[On(CompleteAssetDisposalAction::COMPLETED_EVENT)]
    public function refreshDisposal(): void
    {
        $this->getOwnerRecord()->refresh();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Assets')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'repairTicket']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('asset.code')->label('Code')->badge()->color('gray')
                    ->url(fn (AssetDisposalLine $record): string => AssetResource::getUrl('view', ['record' => $record->asset])),
                TextColumn::make('asset.name')->label('Asset')->wrap()
                    ->description(fn (AssetDisposalLine $record): ?string => $record->notes),
                TextColumn::make('method')->label('Method')->badge(),
                TextColumn::make('repairTicket.number')->label('Repair')->placeholder('—')->toggleable()
                    ->url(fn (AssetDisposalLine $record): ?string => $record->repair_ticket_id === null
                        ? null
                        : RepairTicketResource::getUrl('view', ['record' => $record->repair_ticket_id])),
                TextColumn::make('acquisition_cost')->label('Cost')->alignEnd()->money('IDR')
                    ->state(fn (AssetDisposalLine $record): float => (float) ($record->acquisition_cost ?? $record->asset->acquisition_cost)),
                TextColumn::make('estimated_book_value')->label('Book Value (Estimate)')->alignEnd()->money('IDR')
                    ->state(fn (AssetDisposalLine $record): ?float => $this->estimatedBookValue($record))
                    ->placeholder('Post depreciation first')
                    ->visible(fn (): bool => ! $this->isCompleted()),
                TextColumn::make('commercial_book_value')->label('Book Value')->alignEnd()->money('IDR')
                    ->summarize(Sum::make()->money('IDR')->label('Total'))
                    ->visible(fn (): bool => $this->isCompleted()),
                TextColumn::make('proceeds')->label('Proceeds')->alignEnd()->money('IDR')
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
                TextColumn::make('commercial_gain_loss')->label('Gain / Loss')->alignEnd()->money('IDR')
                    ->color(fn (?string $state): ?string => $state === null ? null : ((float) $state < 0 ? 'danger' : 'success'))
                    ->summarize(Sum::make()->money('IDR')->label('Total'))
                    ->visible(fn (): bool => $this->isCompleted()),
                TextColumn::make('fiscal_book_value')->label('Fiscal Book Value')->alignEnd()->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => $this->isCompleted()),
                TextColumn::make('fiscal_gain_loss')->label('Fiscal Gain / Loss')->alignEnd()->money('IDR')
                    ->summarize(Sum::make()->money('IDR')->label('Total'))
                    ->toggleable()
                    ->visible(fn (): bool => $this->isCompleted()),
            ])
            ->paginated(false);
    }

    private function disposal(): AssetDisposal
    {
        /** @var AssetDisposal $disposal */
        $disposal = $this->getOwnerRecord();

        return $disposal;
    }

    private function isCompleted(): bool
    {
        return $this->disposal()->status === AssetDisposalStatus::Completed;
    }

    private function estimatedBookValue(AssetDisposalLine $line): ?float
    {
        try {
            $value = app(DisposalValuation::class)->value($line->asset, CarbonImmutable::parse($this->disposal()->disposal_date));
        } catch (AssetDisposalException) {
            return null;
        }

        return (float) Money::toRupiah($value['commercial']['book_value']);
    }
}
