<?php

namespace Tests\Feature;

use App\Enums\PlacementType;
use App\Models\AssetAssignment;
use App\Models\AssetAssignmentItem;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\DocumentNumberGenerator;
use App\Services\HandoverDocumentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoverDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_a_pdf_for_the_handover_document(): void
    {
        $branch = Branch::factory()->create();
        $employee = Employee::factory()->for($branch)->create(['name' => 'Budi Santoso']);

        $assignment = AssetAssignment::factory()->for($branch)->create([
            'to_placement_type' => PlacementType::Employee,
            'to_employee_id' => $employee->id,
        ]);

        AssetAssignmentItem::factory()->count(2)->create([
            'asset_assignment_id' => $assignment->id,
        ]);

        $pdf = app(HandoverDocumentGenerator::class)->render($assignment);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_the_filename_is_derived_from_the_document_number(): void
    {
        $assignment = AssetAssignment::factory()->create(['number' => 'BAST/2609/0007']);

        $this->assertSame(
            'BAST-2609-0007.pdf',
            app(HandoverDocumentGenerator::class)->filename($assignment),
        );
    }

    public function test_document_numbers_increase_within_the_same_period(): void
    {
        $generator = app(DocumentNumberGenerator::class);

        $first = $generator->document('assignment', 'BAST');
        $second = $generator->document('assignment', 'BAST');

        $this->assertSame('BAST/'.now()->format('ym').'/0001', $first);
        $this->assertSame('BAST/'.now()->format('ym').'/0002', $second);
    }

    public function test_different_document_types_keep_separate_numbering(): void
    {
        $generator = app(DocumentNumberGenerator::class);

        $generator->document('assignment', 'BAST');
        $checkin = $generator->document('assignment', 'BAP');

        $this->assertSame('BAP/'.now()->format('ym').'/0001', $checkin);
    }

    public function test_the_recipient_name_falls_back_to_a_free_text_name(): void
    {
        $assignment = AssetAssignment::factory()->create([
            'to_placement_type' => PlacementType::Employee,
            'to_employee_id' => null,
            'received_by_name' => 'Tamu Eksternal',
        ]);

        $this->assertSame('Tamu Eksternal', $assignment->recipient_name);
    }
}
