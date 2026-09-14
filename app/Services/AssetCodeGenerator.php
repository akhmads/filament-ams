<?php

namespace App\Services;

use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Builds the permanent asset code from the format held in settings.
 *
 * Recognised tokens: {CATEGORY} {BRANCH} {YY} {YYYY} {MM} {SEQ}
 * Example result: LAP-HO-2609-0123
 */
class AssetCodeGenerator
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
    ) {}

    public function generate(AssetCategory $category, Branch $branch, ?Carbon $date = null): string
    {
        $date ??= Carbon::now();
        $format = (string) Setting::get('asset_code_format', '{CATEGORY}-{BRANCH}-{YY}{MM}-{SEQ}');
        $padding = (int) Setting::get('asset_code_sequence_length', 4);

        $prefix = strtr($format, [
            '{CATEGORY}' => strtoupper($category->prefix),
            '{BRANCH}' => strtoupper($branch->code),
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
        ]);

        // The sequence runs per code pattern rather than globally, so numbering
        // stays tidy as categories and branches are added.
        $scope = str_replace('{SEQ}', '', $prefix);
        $next = $this->numbers->next('asset', $scope);

        return str_replace('{SEQ}', str_pad((string) $next, $padding, '0', STR_PAD_LEFT), $prefix);
    }
}
