<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ExportsReport;
use App\Filament\Pages\Concerns\InteractsWithDepreciationReportFilter;
use App\Filament\Resources\DepreciationPeriods\DepreciationPeriodResource;
use App\Models\DepreciationEntry;
use App\Models\DepreciationPeriod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The journal for one month of one book, one line per asset category: debit the
 * category's depreciation expense account, credit its accumulated depreciation
 * account. Drafts are shown too so the journal can be checked before posting.
 */
class DepreciationJournalReport extends Page implements HasTable
{
    use ExportsReport;
    use InteractsWithDepreciationReportFilter;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Depreciation';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Depreciation Journal';

    protected static ?string $navigationLabel = 'Journal';

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function getSubheading(): ?string
    {
        $period = $this->reportPeriod();
        $book = $this->reportBook()->getLabel();
        $month = $this->reportMonth()->format('F Y');

        return match (true) {
            $period === null => "{$book} book, {$month}: not calculated yet.",
            $period->isPosted() => "{$book} book, {$month}: posted {$period->posted_at?->format('d M Y H:i')} by {$period->postedBy?->name}.",
            default => "{$book} book, {$month}: draft. Post the period before booking this journal.",
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->exportActionGroup(),
            Action::make('openPeriod')
                ->label('Open Period')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->reportPeriod() !== null)
                ->url(fn (): ?string => ($period = $this->reportPeriod())
                    ? DepreciationPeriodResource::getUrl('view', ['record' => $period])
                    : null),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->journalQuery())
            ->defaultKeySort(false)
            ->paginated(false)
            ->emptyStateHeading('No journal for this month')
            ->emptyStateDescription('Calculate the month from Depreciation Periods first.')
            ->columns([
                TextColumn::make('category_name')->label('Category')
                    ->description(fn (DepreciationEntry $record): string => $record->category_code),
                TextColumn::make('asset_count')->label('Assets')->numeric()->alignEnd(),
                TextColumn::make('expense_account')->label('Debit: Depreciation Expense')
                    ->state(fn (DepreciationEntry $record): ?string => $this->account($record->expense_account_code, $record->expense_account_name))
                    ->placeholder('Account not set'),
                TextColumn::make('accumulated_account')->label('Credit: Accumulated Depreciation')
                    ->state(fn (DepreciationEntry $record): ?string => $this->account($record->accumulated_account_code, $record->accumulated_account_name))
                    ->placeholder('Account not set'),
                TextColumn::make('amount')->label('Amount')->money('IDR')->alignEnd()
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
            ])
            ->filters([
                $this->depreciationReportFilter('Month'),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false);
    }

    protected function canExport(): bool
    {
        return $this->reportPeriod() !== null;
    }

    protected function exportHeadings(): array
    {
        return ['Date', 'Account Code', 'Account Name', 'Description', 'Debit', 'Credit'];
    }

    /**
     * Two journal lines per category, dated the last day of the month, in the
     * shape accounting systems import: the debit line and its matching credit line.
     */
    protected function exportRows(): iterable
    {
        $date = $this->reportMonth()->endOfMonth()->startOfDay();
        $month = $this->reportMonth()->format('F Y');

        foreach ($this->getFilteredSortedTableQuery()->get() as $line) {
            $amount = (float) $line->amount;
            $description = "{$this->reportBook()->getLabel()} depreciation {$month}, {$line->category_name} ({$line->asset_count} assets)";

            yield [$date, $line->expense_account_code, $line->expense_account_name, $description, $amount, null];
            yield [$date, $line->accumulated_account_code, $line->accumulated_account_name, $description, null, $amount];
        }
    }

    /**
     * Marks an unposted journal in the file name, so a draft is not booked by mistake.
     */
    protected function exportFilename(): string
    {
        $suffix = $this->reportPeriod()?->isPosted() ? '' : '-draft';

        return "depreciation-journal-{$this->reportBook()->value}-{$this->reportMonth()->format('Y-m')}{$suffix}";
    }

    /**
     * Entries of the chosen period summed per category. Assets and categories are
     * joined without their soft-delete scopes so a removed asset stays in the
     * journal it was depreciated in.
     *
     * @return Builder<DepreciationEntry>
     */
    private function journalQuery(): Builder
    {
        $period = $this->reportPeriod();

        return DepreciationEntry::query()
            ->join('assets', 'assets.id', '=', 'depreciation_entries.asset_id')
            ->join('asset_categories', 'asset_categories.id', '=', 'assets.asset_category_id')
            ->when(
                $period === null,
                fn (Builder $query) => $query->whereRaw('1 = 0'),
                fn (Builder $query) => $query->where('depreciation_entries.depreciation_period_id', $period->getKey()),
            )
            ->selectRaw('min(depreciation_entries.id) as id')
            ->addSelect([
                'asset_categories.code as category_code',
                'asset_categories.name as category_name',
                'asset_categories.expense_account_code',
                'asset_categories.expense_account_name',
                'asset_categories.accumulated_account_code',
                'asset_categories.accumulated_account_name',
            ])
            ->selectRaw('count(*) as asset_count')
            ->selectRaw('sum(depreciation_entries.amount) as amount')
            ->groupBy(
                'asset_categories.id',
                'asset_categories.code',
                'asset_categories.name',
                'asset_categories.expense_account_code',
                'asset_categories.expense_account_name',
                'asset_categories.accumulated_account_code',
                'asset_categories.accumulated_account_name',
            )
            ->orderBy('asset_categories.code');
    }

    private function reportPeriod(): ?DepreciationPeriod
    {
        return DepreciationPeriod::query()
            ->with('postedBy')
            ->where('book', $this->reportBook()->value)
            ->whereDate('period', $this->reportMonth()->toDateString())
            ->first();
    }

    private function account(?string $code, ?string $name): ?string
    {
        return filled($code) ? trim("{$code} {$name}") : null;
    }
}
