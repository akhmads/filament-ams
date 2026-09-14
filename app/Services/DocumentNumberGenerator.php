<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Hands out sequence numbers safely under concurrent writes.
 */
class DocumentNumberGenerator
{
    /**
     * Increments and returns the next number for a key + scope pair.
     */
    public function next(string $key, string $scope = ''): int
    {
        return DB::transaction(function () use ($key, $scope): int {
            $sequence = NumberSequence::query()
                ->where('key', $key)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = NumberSequence::create([
                    'key' => $key,
                    'scope' => $scope,
                    'last_number' => 0,
                ]);

                $sequence = NumberSequence::query()
                    ->whereKey($sequence->getKey())
                    ->lockForUpdate()
                    ->first();
            }

            $sequence->increment('last_number');

            return (int) $sequence->last_number;
        });
    }

    /**
     * A document number shaped PREFIX/YYMM/SEQ, e.g. BAST/2609/0007.
     */
    public function document(string $key, string $prefix, int $padding = 4): string
    {
        $scope = $prefix.'-'.now()->format('ym');
        $next = $this->next($key, $scope);

        return sprintf('%s/%s/%s', $prefix, now()->format('ym'), str_pad((string) $next, $padding, '0', STR_PAD_LEFT));
    }
}
