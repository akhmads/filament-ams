<?php

namespace App\Filament\Pages;

use App\Enums\CalendarEntryKind;
use App\Models\MaintenancePlan;
use App\Models\RepairTicket;
use App\Models\WorkOrder;
use App\Services\MaintenanceCalendarFeed;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * A month grid of maintenance: work orders, repair tickets and plan dates still
 * to come. Weeks start on Monday. Each kind shows only to users who may see it.
 */
class MaintenanceCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Maintenance Calendar';

    protected static ?string $navigationLabel = 'Calendar';

    protected string $view = 'filament.pages.maintenance-calendar';

    /**
     * The month on show as YYYY-MM. Empty or malformed means the current month.
     */
    #[Url]
    public ?string $month = null;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can('viewAny', WorkOrder::class);
    }

    public function getSubheading(): ?string
    {
        return $this->shownMonth()->format('F Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousMonth')
                ->label('Previous')
                ->icon(Heroicon::ChevronLeft)
                ->color('gray')
                ->action(function (): void {
                    $this->month = $this->shownMonth()->subMonth()->format('Y-m');
                }),
            Action::make('currentMonth')
                ->label('This Month')
                ->color('gray')
                ->action(function (): void {
                    $this->month = null;
                }),
            Action::make('nextMonth')
                ->label('Next')
                ->icon(Heroicon::ChevronRight)
                ->iconPosition(IconPosition::After)
                ->color('gray')
                ->action(function (): void {
                    $this->month = $this->shownMonth()->addMonth()->format('Y-m');
                }),
        ];
    }

    public function shownMonth(): CarbonImmutable
    {
        if (is_string($this->month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1) {
            return CarbonImmutable::createFromFormat('!Y-m', $this->month);
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $month = $this->shownMonth();
        $today = CarbonImmutable::today();
        $kinds = $this->visibleKinds();
        $entries = app(MaintenanceCalendarFeed::class)->forMonth($month, $today, $kinds);

        $days = [];
        $lastDay = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();

        for ($day = $month->startOfWeek(CarbonInterface::MONDAY); $day->lte($lastDay); $day = $day->addDay()) {
            $days[] = [
                'date' => $day,
                'isInMonth' => $day->isSameMonth($month),
                'isToday' => $day->isSameDay($today),
                'entries' => $entries[$day->toDateString()] ?? [],
            ];
        }

        return [
            'days' => $days,
            'kinds' => $kinds,
            'weekdays' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];
    }

    /**
     * @return array<int, CalendarEntryKind>
     */
    private function visibleKinds(): array
    {
        $user = Auth::user();

        return array_values(array_filter([
            CalendarEntryKind::WorkOrder,
            $user?->can('viewAny', RepairTicket::class) ? CalendarEntryKind::Repair : null,
            $user?->can('viewAny', MaintenancePlan::class) ? CalendarEntryKind::Planned : null,
        ]));
    }
}
