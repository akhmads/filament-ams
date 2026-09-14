<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\ExportFormat;
use App\Services\SpreadsheetExporter;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An "Export" button offering every {@see ExportFormat}. The export follows what
 * the report currently shows: its filters, search and sort, without paging.
 */
trait ExportsReport
{
    /**
     * @return list<string>
     */
    abstract protected function exportHeadings(): array;

    /**
     * @return iterable<list<string|int|float|DateTimeInterface|null>>
     */
    abstract protected function exportRows(): iterable;

    /**
     * The file name without its extension.
     */
    abstract protected function exportFilename(): string;

    protected function canExport(): bool
    {
        return true;
    }

    protected function exportActionGroup(): ActionGroup
    {
        return ActionGroup::make(
            collect(ExportFormat::cases())
                ->map(fn (ExportFormat $format): Action => Action::make("export{$format->name}")
                    ->label($format->getLabel())
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (): bool => $this->canExport())
                    ->action(fn (): StreamedResponse => app(SpreadsheetExporter::class)->download(
                        $this->exportFilename(),
                        $format,
                        $this->exportHeadings(),
                        $this->exportRows(),
                    )))
                ->all()
        )
            ->label('Export')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->button();
    }
}
