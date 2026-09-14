<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExportFormat: string implements HasLabel
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public function getLabel(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Xlsx => 'Excel (XLSX)',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
