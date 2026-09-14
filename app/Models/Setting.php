<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A key/value store for the things an operator may change without a deploy.
 *
 * Reads are memoised for one request rather than cached across requests. Saving
 * the settings page therefore takes effect immediately, and no cache can go
 * stale if a row is ever changed from outside this class — by a data migration
 * or a manual fix in the database.
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['group', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    /** @var array<string, mixed>|null */
    private static ?array $memo = null;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('setting');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);

        static::$memo = null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        static::$memo = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function allValues(): array
    {
        return static::$memo ??= static::query()->pluck('value', 'key')->all();
    }

    /**
     * Drop the memo — used between tests, and after a write made elsewhere.
     */
    public static function flush(): void
    {
        static::$memo = null;
    }
}
