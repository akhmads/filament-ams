<?php

namespace App\Filament\Actions;

use App\Models\Asset;
use App\Models\LabelTemplate;
use App\Services\LabelSheetGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prints the label for a single asset.
 */
class PrintAssetLabelsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'printLabel';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Print Labels')
            ->icon('heroicon-o-qr-code')
            ->color('gray')
            ->modalHeading('Print Asset Label')
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
            ->action(function (array $data, Asset $record): StreamedResponse {
                $template = LabelTemplate::findOrFail($data['label_template_id']);
                $assets = new Collection([$record]);

                $generator = app(LabelSheetGenerator::class);
                $pdf = $generator->render($assets, $template);
                $generator->markPrinted($assets);

                return response()->streamDownload(
                    fn () => print ($pdf),
                    "label-{$record->code}.pdf",
                );
            });
    }
}
