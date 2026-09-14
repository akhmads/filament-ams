<?php

namespace App\Models;

use App\Enums\LabelCodeType;
use Database\Factories\LabelTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    /** @use HasFactory<LabelTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'width_mm', 'height_mm', 'columns', 'margin_mm', 'font_size_pt',
        'code_type', 'show_logo', 'show_company_name', 'show_asset_name',
        'show_branch', 'show_acquisition_date', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'code_type' => LabelCodeType::class,
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'margin_mm' => 'decimal:2',
            'font_size_pt' => 'decimal:2',
            'show_logo' => 'boolean',
            'show_company_name' => 'boolean',
            'show_asset_name' => 'boolean',
            'show_branch' => 'boolean',
            'show_acquisition_date' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Hanya boleh ada satu template default.
        static::saved(function (LabelTemplate $template): void {
            if ($template->is_default) {
                static::where('id', '!=', $template->id)->update(['is_default' => false]);
            }
        });
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::where('is_active', true)->first();
    }
}
