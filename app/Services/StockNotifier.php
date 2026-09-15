<?php

namespace App\Services;

use App\Models\ItemRequest;
use App\Models\StockItem;
use App\Models\User;
use App\Notifications\ItemRequestDecided;
use App\Notifications\ItemRequestSubmitted;
use App\Notifications\StockBelowMinimum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who hears about stock in the panel's notification bell. As with
 * maintenance, inactive users are never notified and nobody is told about a
 * decision they made themselves.
 */
class StockNotifier
{
    /** Whoever records stock movements looks after restocking. */
    private const STOCK_KEEPER_PERMISSION = 'Create:StockDocument';

    private const REQUEST_APPROVER_PERMISSION = 'Approve:ItemRequest';

    /**
     * Stock falling short is a consequence of a posting, not a decision, so the
     * person who posted it is told as well.
     *
     * @param  Collection<int, StockItem>  $items
     */
    public function stockBelowMinimum(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        Notification::send($this->usersHolding(self::STOCK_KEEPER_PERMISSION), new StockBelowMinimum($items));
    }

    public function itemRequestSubmitted(ItemRequest $request, ?User $submittedBy = null): void
    {
        Notification::send($this->usersHolding(self::REQUEST_APPROVER_PERMISSION, $submittedBy), new ItemRequestSubmitted($request));
    }

    /**
     * Tells whoever recorded the request that it was approved or rejected.
     */
    public function itemRequestDecided(ItemRequest $request, User $decidedBy): void
    {
        if ($request->created_by === null || (int) $request->created_by === $decidedBy->id) {
            return;
        }

        User::query()
            ->where('is_active', true)
            ->find((int) $request->created_by)
            ?->notify(new ItemRequestDecided($request));
    }

    /**
     * @return Collection<int, User>
     */
    private function usersHolding(string $permission, ?User $except = null): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->holdingPermission($permission)
            ->when($except !== null, fn (Builder $query) => $query->whereKeyNot($except->getKey()))
            ->get();
    }
}
