<?php

namespace App\Exceptions;

use App\Enums\DepreciationBook;
use Carbon\CarbonImmutable;
use Exception;

class DepreciationException extends Exception
{
    public static function alreadyPosted(DepreciationBook $book, CarbonImmutable $month): self
    {
        return new self("{$book->getLabel()} depreciation for {$month->format('F Y')} is already posted and can no longer be recalculated.");
    }

    public static function lockedByLaterPosting(DepreciationBook $book, CarbonImmutable $month, CarbonImmutable $lastPosted): self
    {
        return new self("{$book->getLabel()} depreciation is posted up to {$lastPosted->format('F Y')}, so {$month->format('F Y')} is locked.");
    }

    public static function outOfSequence(DepreciationBook $book, CarbonImmutable $expected, CarbonImmutable $month): self
    {
        return new self("{$book->getLabel()} depreciation must be posted month by month: post {$expected->format('F Y')} before {$month->format('F Y')}.");
    }
}
