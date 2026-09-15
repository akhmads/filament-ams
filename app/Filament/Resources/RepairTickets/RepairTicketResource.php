<?php

namespace App\Filament\Resources\RepairTickets;

use App\Enums\RepairTicketStatus;
use App\Filament\Resources\RepairTickets\Pages\CreateRepairTicket;
use App\Filament\Resources\RepairTickets\Pages\EditRepairTicket;
use App\Filament\Resources\RepairTickets\Pages\ListRepairTickets;
use App\Filament\Resources\RepairTickets\Pages\ViewRepairTicket;
use App\Filament\Resources\RepairTickets\Schemas\RepairTicketForm;
use App\Filament\Resources\RepairTickets\Schemas\RepairTicketInfolist;
use App\Filament\Resources\RepairTickets\Tables\RepairTicketsTable;
use App\Filament\Resources\WorkOrders\RelationManagers\SparePartsRelationManager;
use App\Models\RepairTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class RepairTicketResource extends Resource
{
    protected static ?string $model = RepairTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static string|UnitEnum|null $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Repair Ticket';

    protected static ?string $pluralModelLabel = 'Repair Tickets';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $reported = static::getModel()::query()->where('status', RepairTicketStatus::Reported->value)->count();

        return $reported > 0 ? (string) $reported : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Damage reports waiting to be verified';
    }

    public static function form(Schema $schema): Schema
    {
        return RepairTicketForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RepairTicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RepairTicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SparePartsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRepairTickets::route('/'),
            'create' => CreateRepairTicket::route('/create'),
            'view' => ViewRepairTicket::route('/{record}'),
            'edit' => EditRepairTicket::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['asset', 'assignedTo']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
