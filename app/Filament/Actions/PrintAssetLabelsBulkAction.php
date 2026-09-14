<?php

namespace App\Filament\Actions;

use App\Models\LabelTemplate;
use App\Services\LabelSheetGenerator;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prints labels for many assets at once from the asset list.
 */
class PrintAssetLabelsBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'printLabels';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Print Labels')
            ->icon('heroicon-o-qr-code')
            ->color('gray')
            ->deselectRecordsAfterCompletion()
            ->modalHeading('Print Labels for Selected Assets')
            ->modalSubmitActionLabel('Download PDF')
            ->schema([
                Select::make('label_template_id')
                    ->label('Label Template')
                    ->options(fn (): array => LabelTemplate::query()
                        ->where('is_active', true)
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?int => LabelTemplate::default()?->id)
                    ->required(),
            ])
            ->action(function (array $data, Collection $records): StreamedResponse {
                $template = LabelTemplate::findOrFail($data['label_template_id']);

                $generator = app(LabelSheetGenerator::class);
                $pdf = $generator->render($records, $template);
                $generator->markPrinted($records);

                return response()->streamDownload(
                    fn () => print ($pdf),
                    'asset-labels-'.now()->format('Ymd-His').'.pdf',
                );
            });
    }
}
