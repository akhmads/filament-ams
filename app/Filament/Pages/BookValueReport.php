<?php

namespace App\Filament\Pages;

use App\Enums\DepreciationPeriodStatus;
use App\Filament\Pages\Concerns\ExportsReport;
use App\Filament\Pages\Concerns\InteractsWithDepreciationReportFilter;
use App\Models\Asset;
use App\Models\DepreciationEntry;
use App\Models\DepreciationPeriod;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Cost, accumulated depreciation and book value of every depreciable asset at the
 * end of a month. Only posted depreciation counts, so a draft under review never
 * changes the figures.
 */
class BookValueReport extends Page implements HasTable
{
    use ExportsReport;
    use InteractsWithDepreciationReportFilter;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Depreciation';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Book Value';

    protected static ?string $navigationLabel = 'Book Value';

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function getSubheading(): ?string
    {
        $book = $this->reportBook()->getLabel();
        $postedThrough = $this->postedThrough();

        return $postedThrough === null
            ? "{$book} book: nothing posted up to this month yet, so assets are shown at cost."
            : "{$book} book, using depreciation posted through {$postedThrough->format('F Y')}.";
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->exportActionGroup(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->bookValueQuery())
            ->defaultSort('code')
            ->emptyStateHeading('No depreciable assets')
            ->emptyStateDescription('No depreciable asset was acquired by the end of this month.')
            ->columns([
                TextColumn::make('code')->label('Asset Code')->badge()->color('gray')->searchable()->sortable(),
                TextColumn::make('name')->label('Asset')->searchable()->wrap(),
                TextColumn::make('category.name')->label('Category')->toggleable(),
                TextColumn::make('branch.name')->label('Branch')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('acquisition_date')->label('Acquired')->date('d M Y')->sortable(),
                TextColumn::make('acquisition_cost')->label('Cost')->money('IDR')->alignEnd()->sortable()
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
                TextColumn::make('accumulated_depreciation')->label('Accumulated')->money('IDR')->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('accumulated_depreciation', $direction))
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
                TextColumn::make('book_value')->label('Book Value')->money('IDR')->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('book_value', $direction))
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
                TextColumn::make('status')->label('Status')->badge()->toggleable(),
            ])
            ->filters([
                $this->depreciationReportFilter('As of Month'),
                SelectFilter::make('asset_category_id')->label('Category')->relationship('category', 'name'),
                SelectFilter::make('branch_id')->label('Branch')->relationship('branch', 'name'),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->paginated([25, 50, 100]);
    }

    protected function exportHeadings(): array
    {
        return ['Asset Code', 'Asset Name', 'Category', 'Branch', 'Acquisition Date', 'Cost', 'Accumulated Depreciation', 'Book Value', 'Status'];
    }

    /**
     * The table's own query, so the export matches the filters, search and sort on
     * screen. The table always ends its sort on the asset id, which keeps the
     * chunks stable.
     */
    protected function exportRows(): iterable
    {
        return $this->getFilteredSortedTableQuery()
            ->with(['category', 'branch'])
            ->lazy(500)
            ->map(fn (Asset $asset): array => [
                $asset->code,
                $asset->name,
                $asset->category?->name,
                $asset->branch?->name,
                $asset->acquisition_date,
                (float) $asset->acquisition_cost,
                (float) $asset->accumulated_depreciation,
                (float) $asset->book_value,
                $asset->status->getLabel(),
            ]);
    }

    protected function exportFilename(): string
    {
        return "book-value-{$this->reportBook()->value}-{$this->reportMonth()->format('Y-m')}";
    }

    /**
     * Assets acquired by the end of the month, each with the accumulated
     * depreciation of its latest posted entry up to that month. Posting runs month
     * by month, so every entry up to the last posted month is posted.
     *
     * @return Builder<Asset>
     */
    private function bookValueQuery(): Builder
    {
        $postedThrough = $this->postedThrough();

        $latestAccumulated = DepreciationEntry::query()
            ->select('depreciation_entries.accumulated')
            ->whereColumn('depreciation_entries.asset_id', 'assets.id')
            ->where('depreciation_entries.book', $this->reportBook()->value)
            ->when(
                $postedThrough === null,
                fn (Builder $query) => $query->whereRaw('1 = 0'),
                fn (Builder $query) => $query->where('depreciation_entries.period', '<', $postedThrough->addMonth()->toDateString()),
            )
            ->orderByDesc('depreciation_entries.period')
            ->limit(1)
            ->toBase();

        $accumulated = "coalesce(({$latestAccumulated->toSql()}), 0)";

        return Asset::query()
            ->select('assets.*')
            ->selectRaw("{$accumulated} as accumulated_depreciation", $latestAccumulated->getBindings())
            ->selectRaw("assets.acquisition_cost - {$accumulated} as book_value", $latestAccumulated->getBindings())
            ->where('assets.is_depreciable', true)
            ->where('assets.acquisition_date', '<', $this->reportMonth()->addMonth()->toDateString());
    }

    /**
     * The last month whose depreciation counts: the last posted month, capped at
     * the report month. Null when nothing is posted for the book yet.
     */
    private function postedThrough(): ?CarbonImmutable
    {
        $lastPosted = DepreciationPeriod::query()
            ->where('book', $this->reportBook()->value)
            ->where('status', DepreciationPeriodStatus::Posted->value)
            ->max('period');

        if ($lastPosted === null) {
            return null;
        }

        $lastPosted = CarbonImmutable::parse($lastPosted)->startOfMonth();

        return $lastPosted->min($this->reportMonth());
    }
}
