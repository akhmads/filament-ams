<?php

namespace App\Models\Asset;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Position extends Model
{
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
