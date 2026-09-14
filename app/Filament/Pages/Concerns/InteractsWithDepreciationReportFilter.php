<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\DepreciationBook;
use App\Models\DepreciationPeriod;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Support\Facades\Auth;

/**
 * The book and month every depreciation report is read for. The filter only
 * carries the state; each report builds its own query from it, because the
 * month changes which entries are selected rather than narrowing a list.
 */
trait InteractsWithDepreciationReportFilter
{
    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can('viewAny', DepreciationPeriod::class);
    }

    protected function depreciationReportFilter(string $monthLabel): Filter
    {
        return Filter::make('report')
            ->schema([
                Select::make('book')
                    ->label('Book')
                    ->options(DepreciationBook::class)
                    ->default(DepreciationBook::Commercial->value)
                    ->selectablePlaceholder(false),
                DatePicker::make('month')
                    ->label($monthLabel)
                    ->native(false)
                    ->displayFormat('F Y')
                    ->closeOnDateSelection()
                    ->default(now()->startOfMonth()->toDateString()),
            ])
            ->columns(2)
            ->indicateUsing(fn (): array => [
                Indicator::make("{$this->reportBook()->getLabel()} · {$this->reportMonth()->format('F Y')}")->removable(false),
            ]);
    }

    protected function reportBook(): DepreciationBook
    {
        $book = $this->getTableFilterState('report')['book'] ?? null;

        return $book instanceof DepreciationBook
            ? $book
            : (DepreciationBook::tryFrom((string) $book) ?? DepreciationBook::Commercial);
    }

    protected function reportMonth(): CarbonImmutable
    {
        $month = $this->getTableFilterState('report')['month'] ?? null;

        return filled($month)
            ? CarbonImmutable::parse($month)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
    }
}
