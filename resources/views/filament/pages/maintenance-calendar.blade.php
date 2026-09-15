@use('App\Enums\CalendarEntryKind')

<x-filament-panels::page>
    {{-- The panel has no custom Tailwind theme, so the grid is styled here with Filament's colour variables. --}}
    <style>
        .ams-calendar-legend { display: flex; flex-wrap: wrap; gap: 0.25rem 1.5rem; font-size: 0.875rem; color: var(--gray-600); }
        .dark .ams-calendar-legend { color: var(--gray-400); }
        .ams-calendar-legend strong { color: var(--gray-950); font-weight: 600; }
        .dark .ams-calendar-legend strong { color: var(--gray-50); }
        .ams-calendar-scroll { overflow-x: auto; border-radius: 0.75rem; }
        .ams-calendar { display: grid; grid-template-columns: repeat(7, minmax(7rem, 1fr)); min-width: 49rem; border-top: 1px solid var(--gray-200); border-left: 1px solid var(--gray-200); background: #fff; }
        .dark .ams-calendar { background: var(--gray-900); border-color: rgb(255 255 255 / 0.1); }
        .ams-calendar__weekday { padding: 0.5rem 0.75rem; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: var(--gray-500); background: var(--gray-50); border-right: 1px solid var(--gray-200); border-bottom: 1px solid var(--gray-200); }
        .dark .ams-calendar__weekday { color: var(--gray-400); background: rgb(255 255 255 / 0.05); border-color: rgb(255 255 255 / 0.1); }
        .ams-calendar__day { display: flex; flex-direction: column; gap: 0.25rem; min-width: 0; min-height: 7.5rem; padding: 0.375rem; border-right: 1px solid var(--gray-200); border-bottom: 1px solid var(--gray-200); }
        .dark .ams-calendar__day { border-color: rgb(255 255 255 / 0.1); }
        .ams-calendar__day.is-outside { background: var(--gray-50); }
        .dark .ams-calendar__day.is-outside { background: rgb(255 255 255 / 0.02); }
        .ams-calendar__date { display: inline-grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 600; color: var(--gray-700); }
        .dark .ams-calendar__date { color: var(--gray-200); }
        .ams-calendar__day.is-outside .ams-calendar__date { color: var(--gray-400); }
        .ams-calendar__day.is-today .ams-calendar__date { background: var(--primary-600); color: #fff; }
        .ams-calendar__entries { display: flex; flex-direction: column; gap: 0.25rem; max-height: 10rem; overflow-y: auto; }
        .ams-calendar__entry { display: block; padding: 0.25rem 0.375rem; border-left: 3px solid var(--entry-color); border-radius: 0.375rem; background: color-mix(in oklab, var(--entry-color) 12%, transparent); font-size: 0.75rem; line-height: 1.25; color: var(--gray-950); text-decoration: none; }
        .dark .ams-calendar__entry { color: var(--gray-50); }
        .ams-calendar__entry:hover { background: color-mix(in oklab, var(--entry-color) 24%, transparent); }
        .ams-calendar__entry.is-planned { border-left-style: dashed; background: transparent; box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--entry-color) 30%, transparent); }
        .ams-calendar__entry-title, .ams-calendar__entry-detail { display: block; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .ams-calendar__entry-title { font-weight: 500; }
        .ams-calendar__entry-detail { color: var(--gray-500); }
        .dark .ams-calendar__entry-detail { color: var(--gray-400); }
    </style>

    <div class="ams-calendar-legend">
        @foreach ($kinds as $kind)
            <span><strong>{{ $kind->getLabel() }}</strong> {{ $kind->description() }}</span>
        @endforeach
        <span>Overdue work orders are shown in red; planned dates have a dashed edge.</span>
    </div>

    <div class="ams-calendar-scroll">
        <div class="ams-calendar">
            @foreach ($weekdays as $weekday)
                <div class="ams-calendar__weekday">{{ $weekday }}</div>
            @endforeach

            @foreach ($days as $day)
                <div
                    wire:key="day-{{ $day['date']->toDateString() }}"
                    @class([
                        'ams-calendar__day',
                        'is-outside' => ! $day['isInMonth'],
                        'is-today' => $day['isToday'],
                    ])
                >
                    <span class="ams-calendar__date">{{ $day['date']->day }}</span>

                    @if ($day['entries'] !== [])
                        <div class="ams-calendar__entries">
                            @foreach ($day['entries'] as $entry)
                                <a
                                    href="{{ $entry->url }}"
                                    title="{{ $entry->title }}{{ $entry->detail ? ' — '.$entry->detail : '' }} ({{ $entry->statusLabel }})"
                                    style="--entry-color: var(--{{ $entry->color }}-500)"
                                    @class([
                                        'ams-calendar__entry',
                                        'is-planned' => $entry->kind === CalendarEntryKind::Planned,
                                    ])
                                >
                                    <span class="ams-calendar__entry-title">{{ $entry->title }}</span>
                                    @if ($entry->detail)
                                        <span class="ams-calendar__entry-detail">{{ $entry->detail }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
