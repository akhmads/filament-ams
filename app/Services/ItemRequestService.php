<?php

namespace App\Services;

use App\Enums\ItemRequestStatus;
use App\Exceptions\ItemRequestException;
use App\Exceptions\StockException;
use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves an item request through submitted → approved → issued. A submitted
 * request can be rejected, and an open one cancelled.
 *
 * Employees have no login, so staff record the request on the employee's behalf;
 * someone holding the approval permission signs it off before goods leave stock.
 */
class ItemRequestService
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly StockDocumentService $documents,
        private readonly StockNotifier $notifier,
    ) {}

    /**
     * An item request number shaped REQ/YYMM/SEQ.
     */
    public function nextNumber(): string
    {
        return $this->numbers->document('item_request', 'REQ');
    }

    public function approve(ItemRequest $request, User $approvedBy): ItemRequest
    {
        $this->ensureStatus($request, 'approved', ItemRequestStatus::Submitted);

        if (! $request->lines()->exists()) {
            throw ItemRequestException::noLines($request);
        }

        $request->forceFill([
            'status' => ItemRequestStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approvedBy->id,
        ])->save();

        $this->notifier->itemRequestDecided($request, $approvedBy);

        return $request;
    }

    public function reject(ItemRequest $request, string $reason, User $rejectedBy): ItemRequest
    {
        $this->ensureStatus($request, 'rejected', ItemRequestStatus::Submitted);

        $request->forceFill([
            'status' => ItemRequestStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $rejectedBy->id,
            'rejection_reason' => $reason,
        ])->save();

        $this->notifier->itemRequestDecided($request, $rejectedBy);

        return $request;
    }

    /**
     * Withdraws a request that is no longer needed, before any goods are issued.
     */
    public function cancel(ItemRequest $request, string $reason): ItemRequest
    {
        $this->ensureStatus($request, 'cancelled', ItemRequestStatus::Submitted, ItemRequestStatus::Approved);

        $request->forceFill([
            'status' => ItemRequestStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        return $request;
    }

    /**
     * Issues every requested line from one warehouse and posts the issue. When the
     * warehouse cannot cover the whole request nothing is issued and the request
     * stays approved.
     */
    public function fulfill(ItemRequest $request, Location $warehouse, User $issuedBy): ItemRequest
    {
        $this->ensureStatus($request, 'issued', ItemRequestStatus::Approved);

        $lines = $request->lines()->orderBy('id')->get();

        if ($lines->isEmpty()) {
            throw ItemRequestException::noLines($request);
        }

        return DB::transaction(function () use ($request, $warehouse, $issuedBy, $lines): ItemRequest {
            try {
                $document = $this->documents->issue(
                    warehouse: $warehouse,
                    lines: $lines->map(fn (ItemRequestLine $line): array => [
                        'stock_item_id' => $line->stock_item_id,
                        'quantity' => $line->quantity,
                        'notes' => $line->notes,
                    ])->all(),
                    issuedBy: $issuedBy,
                    source: $request,
                    employeeId: $request->employee_id,
                    departmentId: $request->department_id,
                    notes: "Item request {$request->number}",
                );
            } catch (StockException $exception) {
                throw ItemRequestException::cannotIssue($request, $exception);
            }

            $request->forceFill([
                'status' => ItemRequestStatus::Fulfilled,
                'fulfilled_at' => now(),
                'stock_document_id' => $document->id,
            ])->save();

            return $request;
        });
    }

    private function ensureStatus(ItemRequest $request, string $action, ItemRequestStatus ...$allowed): void
    {
        if (! in_array($request->status, $allowed, strict: true)) {
            throw ItemRequestException::notInStatus($request, $action, ...$allowed);
        }
    }
}
