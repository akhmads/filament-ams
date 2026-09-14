<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\Asset;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('code')->label('Asset Code')->badge()->copyable(),
                            TextEntry::make('name')->label('Name')->columnSpan(2),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('category.full_name')->label('Category'),
                            TextEntry::make('brand.name')->label('Brand')->placeholder('—'),
                            TextEntry::make('assetModel.name')->label('Model')->placeholder('—'),
                            TextEntry::make('condition')->label('Condition')->badge(),
                            TextEntry::make('serial_number')->label('Serial Number')->placeholder('—')->copyable(),
                            TextEntry::make('manufacture_year')->label('Year of Manufacture')->placeholder('—'),
                            TextEntry::make('parent.code')->label('Parent Asset')->placeholder('—'),
                            TextEntry::make('notes')->label('Notes')->placeholder('—'),
                        ]),
                    ]),
                Section::make('Current Placement')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('placement_type')->label('Placement Type')->badge(),
                            TextEntry::make('current_holder')->label('Located At'),
                            TextEntry::make('branch.name')->label('Branch'),
                            TextEntry::make('department.name')->label('Department')->placeholder('—'),
                        ]),
                    ]),
                Section::make('Acquisition')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('acquisition_date')->label('Date')->date('d M Y'),
                            TextEntry::make('acquisition_cost')->label('Cost')->money('IDR'),
                            TextEntry::make('supplier.name')->label('Supplier')->placeholder('—'),
                            TextEntry::make('funding_source')->label('Funding Source')->placeholder('—'),
                            TextEntry::make('po_number')->label('PO Number')->placeholder('—'),
                            TextEntry::make('invoice_number')->label('Invoice Number')->placeholder('—'),
                        ]),
                    ]),
                Section::make('Depreciation & Warranty')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('depreciation_method')->label('Depreciation Method')->badge(),
                            TextEntry::make('useful_life_months')->label('Useful Life')->suffix(' months'),
                            TextEntry::make('residual_value')->label('Residual Value')->money('IDR'),
                            TextEntry::make('fiscal_group')->label('Tax Asset Group')->badge()
                                ->placeholder(fn ($record): string => $record->category?->fiscal_group
                                    ? 'From category: '.$record->category->fiscal_group->getLabel()
                                    : 'Not set'),
                            TextEntry::make('fiscal_method')->label('Tax Method')->badge(),
                            TextEntry::make('depreciation_start_date')->label('Depr. Start')->date('d M Y')->placeholder('—'),
                            TextEntry::make('warranty_start')->label('Warranty Starts')->date('d M Y')->placeholder('—'),
                            TextEntry::make('warranty_end')
                                ->label('Warranty Ends')
                                ->date('d M Y')
                                ->placeholder('—')
                                ->badge()
                                ->color(fn (Asset $record): string => $record->isUnderWarranty() ? 'success' : 'danger'),
                            TextEntry::make('warranty_vendor')->label('Warranty Vendor')->placeholder('—'),
                        ]),
                    ]),
                Section::make('Specifications')
                    ->visible(fn (Asset $record): bool => filled($record->specs))
                    ->schema([
                        TextEntry::make('specs')
                            ->hiddenLabel()
                            ->state(fn (Asset $record): string => collect($record->specs ?? [])
                                ->map(fn ($value, $key): string => str($key)->headline()->toString().': '.$value)
                                ->implode(' · ')),
                    ]),
                Section::make('Attachments')
                    ->collapsed()
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('photos')
                            ->label('Photo')
                            ->collection('photos')
                            ->conversion('thumb')
                            ->placeholder('No photos yet'),
                    ]),
            ]);
    }
}
