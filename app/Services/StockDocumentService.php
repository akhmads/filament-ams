<?php

namespace App\Services;

use App\Enums\RepairTicketStatus;
use App\Enums\StockDocumentType;
use App\Enums\WorkOrderStatus;
use App\Exceptions\StockException;
use App\Models\Location;
use App\Models\RepairTicket;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Numbers stock documents and raises the goods issues other modules need: spare
 * parts for maintenance work, and goods for an approved item request.
 */
class StockDocumentService
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly StockLedger $ledger,
    ) {}

    /**
     * A document number shaped PREFIX/YYMM/SEQ, with a prefix per document type.
     */
    public function nextNumber(StockDocumentType $type): string
    {
        return $this->numbers->document('stock_document', $type->prefix());
    }

    /**
     * Issues spare parts to a work order or repair that is under way, and posts the
     * issue at once so stock drops as the parts are used.
     *
     * @param  list<array{stock_item_id: int|string, quantity: string|int|float, notes?: ?string}>  $parts
     */
    public function issueSpareParts(WorkOrder|RepairTicket $work, Location $warehouse, array $parts, User $issuedBy, ?string $notes = null): StockDocument
    {
        $isUnderWay = $work instanceof WorkOrder
            ? $work->status === WorkOrderStatus::InProgress
            : $work->status === RepairTicketStatus::InRepair;

        if (! $isUnderWay) {
            throw StockException::workNotInProgress(($work instanceof WorkOrder ? 'work order ' : 'repair ticket ').$work->number);
        }

        return $this->issue(
            warehouse: $warehouse,
            lines: $parts,
            issuedBy: $issuedBy,
            source: $work,
            notes: filled($notes) ? $notes : "Spare parts for {$work->number}",
        );
    }

    /**
     * Creates and posts a goods issue in one go. Nothing is kept when posting
     * fails, not even the document number.
     *
     * @param  list<array{stock_item_id: int|string, quantity: string|int|float, notes?: ?string}>  $lines
     */
    public function issue(
        Location $warehouse,
        array $lines,
        User $issuedBy,
        ?Model $source = null,
        ?int $employeeId = null,
        ?int $departmentId = null,
        ?string $notes = null,
    ): StockDocument {
        return DB::transaction(function () use ($warehouse, $lines, $issuedBy, $source, $employeeId, $departmentId, $notes): StockDocument {
            $document = new StockDocument([
                'number' => $this->nextNumber(StockDocumentType::Issue),
                'type' => StockDocumentType::Issue,
                'document_date' => today()->toDateString(),
                'location_id' => $warehouse->id,
                'employee_id' => $employeeId,
                'department_id' => $departmentId,
                'notes' => $notes,
                'created_by' => $issuedBy->id,
            ]);

            if ($source !== null) {
                $document->source()->associate($source);
            }

            $document->save();

            $document->lines()->createMany(collect($lines)
                ->map(fn (array $line): array => [
                    'stock_item_id' => (int) $line['stock_item_id'],
                    'quantity' => (string) $line['quantity'],
                    'notes' => $line['notes'] ?? null,
                ])
                ->all());

            return $this->ledger->post($document, $issuedBy);
        });
    }
}
