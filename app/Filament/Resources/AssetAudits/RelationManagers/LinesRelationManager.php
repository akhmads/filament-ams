<?php

namespace App\Filament\Resources\AssetAudits\RelationManagers;

use App\Enums\AssetAuditStatus;
use App\Enums\AuditFollowUp;
use App\Enums\AuditResult;
use App\Exceptions\AssetAuditException;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\AssetAudit;
use App\Models\AssetAuditLine;
use App\Services\AssetAuditService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Every asset in the audit with what was found. Once counting finishes, a holder
 * of the close permission picks the corrections here; they are applied when the
 * audit is closed.
 */
class LinesRelationManager extends RelationManager
{
    /**
     * Dispatched by the audit actions so the corrections unlock or lock without a reload.
     */
    public const STATUS_CHANGED_EVENT = 'asset-audit-status-changed';

    protected static string $relationship = 'lines';

    protected static ?string $title = 'Assets';

    #[On(self::STATUS_CHANGED_EVENT)]
    public function refreshAudit(): void
    {
        $this->getOwnerRecord()->refresh();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Assets')
            ->description(fn (): string => match ($this->audit()->status) {
                AssetAuditStatus::InProgress => 'Assets not scanned yet become missing when counting finishes.',
                AssetAuditStatus::UnderReview => 'Choose the corrections to apply. Nothing changes on the register until the audit is closed.',
                default => 'Corrections shown were applied when the audit closed.',
            })
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'expectedLocation', 'scannedLocation', 'scannedBy']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('asset.code')->label('Code')->badge()->color('gray')->searchable()
                    ->url(fn (AssetAuditLine $record): string => AssetResource::getUrl('view', ['record' => $record->asset])),
                TextColumn::make('asset.name')->label('Asset')->wrap()->searchable(),
                TextColumn::make('result')->label('Result')->badge()
                    ->description(fn (AssetAuditLine $record): ?string => $record->is_expected ? null : 'Not on the list'),
                TextColumn::make('expectedLocation.name')->label('Recorded In')->placeholder('With an employee'),
                TextColumn::make('scannedLocation.name')->label('Found In')->placeholder('—'),
                TextColumn::make('expected_condition')->label('Recorded Condition')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('observed_condition')->label('Found Condition')->badge()->placeholder('—')
                    ->color(fn (AssetAuditLine $record): ?string => $record->hasConditionChanged() ? 'danger' : 'gray'),
                TextColumn::make('scannedBy.name')->label('Scanned By')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('scanned_at')->label('Scanned At')->dateTime('d M Y H:i')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                $this->followUpColumn(AuditFollowUp::Relocate, 'Move'),
                $this->followUpColumn(AuditFollowUp::UpdateCondition, 'Update Condition'),
                $this->followUpColumn(AuditFollowUp::MarkLost, 'Mark Lost'),
                TextColumn::make('applied_at')->label('Applied')->dateTime('d M Y H:i')->placeholder('—')
                    ->visible(fn (): bool => $this->audit()->status === AssetAuditStatus::Completed),
            ])
            ->filters([
                SelectFilter::make('result')
                    ->label('Result')
                    ->options(AuditResult::class),
                Filter::make('findings')
                    ->label('Findings only')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
                        ->where('result', '!=', AuditResult::Found->value)
                        ->orWhere('is_expected', false)
                        ->orWhereColumn('observed_condition', '!=', 'expected_condition'))),
            ])
            ->paginated([25, 50, 100]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function followUpColumn(AuditFollowUp $followUp, string $label): ToggleColumn
    {
        return ToggleColumn::make($followUp->value)
            ->label($label)
            ->visible(fn (): bool => in_array($this->audit()->status, [AssetAuditStatus::UnderReview, AssetAuditStatus::Completed], strict: true))
            ->disabled(fn (AssetAuditLine $record): bool => ! $this->canChoose($record, $followUp))
            ->updateStateUsing(fn (AssetAuditLine $record, bool $state): bool => $this->choose($record, $followUp, $state));
    }

    private function audit(): AssetAudit
    {
        /** @var AssetAudit $audit */
        $audit = $this->getOwnerRecord();

        return $audit;
    }

    private function canChoose(AssetAuditLine $line, AuditFollowUp $followUp): bool
    {
        return $this->audit()->status === AssetAuditStatus::UnderReview
            && (bool) Auth::user()?->can('close', $this->audit())
            && $followUp->isApplicableTo($line);
    }

    private function choose(AssetAuditLine $line, AuditFollowUp $followUp, bool $isChosen): bool
    {
        if (! $this->canChoose($line, $followUp)) {
            return (bool) $line->{$followUp->value};
        }

        try {
            app(AssetAuditService::class)->setFollowUp($line, $followUp, $isChosen);
        } catch (AssetAuditException $exception) {
            Notification::make()->title('Correction not changed')->body($exception->getMessage())->danger()->send();
        }

        return (bool) $line->{$followUp->value};
    }
}
