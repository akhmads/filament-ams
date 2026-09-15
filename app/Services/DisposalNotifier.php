<?php

namespace App\Services;

use App\Models\AssetDisposal;
use App\Models\User;
use App\Notifications\AssetDisposalDecided;
use App\Notifications\AssetDisposalProposed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who hears about disposals in the panel's notification bell. Inactive
 * users are never notified, and nobody is told about their own decision.
 */
class DisposalNotifier
{
    private const APPROVER_PERMISSION = 'Approve:AssetDisposal';

    public function disposalProposed(AssetDisposal $disposal, ?User $proposedBy = null): void
    {
        $approvers = User::query()
            ->where('is_active', true)
            ->holdingPermission(self::APPROVER_PERMISSION)
            ->when($proposedBy !== null, fn (Builder $query) => $query->whereKeyNot($proposedBy->getKey()))
            ->get();

        Notification::send($approvers, new AssetDisposalProposed($disposal));
    }

    /**
     * Tells whoever proposed the disposal that it was approved or rejected.
     */
    public function disposalDecided(AssetDisposal $disposal, User $decidedBy): void
    {
        if ($disposal->created_by === null || (int) $disposal->created_by === $decidedBy->id) {
            return;
        }

        User::query()
            ->where('is_active', true)
            ->find((int) $disposal->created_by)
            ?->notify(new AssetDisposalDecided($disposal));
    }
}
