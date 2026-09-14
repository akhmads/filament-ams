<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves a nested-set node's full path — "Head Office › Main Building › IT Room".
 *
 * The path is built from a per-request map of the whole table rather than by
 * reading the `ancestors` relation. Two reasons: the relation would have to be
 * eager loaded at every call site or lazy loading throws, and labelling a select
 * of fifty options would otherwise cost fifty queries. One query covers the whole
 * request no matter how many paths are rendered.
 *
 * An already-loaded `ancestors` relation is still used when present, so callers
 * that do eager load it pay nothing extra.
 */
trait HasTreePath
{
    public const TREE_SEPARATOR = ' › ';

    /**
     * Keyed by model class, then by id.
     *
     * @var array<class-string, array<int, array{name: string, parent_id: int|null}>>
     */
    private static array $treePathMemo = [];

    protected static function bootHasTreePath(): void
    {
        $forget = static fn (Model $model): null => static::forgetTreePath();

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Drop the memo — the tree changed, or a test is starting.
     */
    public static function forgetTreePath(): void
    {
        unset(self::$treePathMemo[static::class]);
    }

    public function getFullNameAttribute(): string
    {
        if ($this->relationLoaded('ancestors')) {
            return $this->ancestors
                ->pluck('name')
                ->push($this->name)
                ->implode(self::TREE_SEPARATOR);
        }

        if (! $this->exists) {
            return (string) $this->name;
        }

        $nodes = static::treePathMap();
        $names = [];
        $id = $this->getKey();

        // The depth guard is cheap insurance: a cycle introduced by a bad import
        // would otherwise hang the request rather than fail.
        for ($depth = 0; $depth < 20 && $id !== null && isset($nodes[$id]); $depth++) {
            array_unshift($names, $nodes[$id]['name']);
            $id = $nodes[$id]['parent_id'];
        }

        return $names === [] ? (string) $this->name : implode(self::TREE_SEPARATOR, $names);
    }

    /**
     * @return array<int, array{name: string, parent_id: int|null}>
     */
    private static function treePathMap(): array
    {
        return self::$treePathMemo[static::class] ??= static::query()
            ->get(['id', 'name', 'parent_id'])
            ->mapWithKeys(fn (Model $node): array => [
                $node->getKey() => ['name' => $node->name, 'parent_id' => $node->parent_id],
            ])
            ->all();
    }
}
