<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A handover document (BAST). One document may carry many assets.
     */
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('type', 20)->comment('checkout|checkin|transfer');
            $table->date('assignment_date');
            $table->date('expected_return_date')->nullable()->comment('Filled in when the asset is only borrowed');
            $table->dateTime('completed_at')->nullable();

            $table->foreignId('branch_id')->constrained()->restrictOnDelete();

            // Destination placement
            $table->string('to_placement_type', 20);
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();

            // Who hands over and who receives
            $table->foreignId('handed_over_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('received_by_name')->nullable()->comment('Filled in when the recipient is not a registered employee');
            $table->longText('handover_signature')->nullable()->comment('Handover signature as a PNG data URL');
            $table->longText('receiver_signature')->nullable()->comment('Recipient signature as a PNG data URL');

            $table->string('status', 20)->default('draft')->comment('draft|completed|cancelled');
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'assignment_date']);
            $table->index('expected_return_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
