<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corrective repair of a damaged asset, from the damage report to its outcome.
     */
    public function up(): void
    {
        Schema::create('repair_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal')->comment('low|normal|high|urgent');
            $table->string('status', 20)->default('reported')->comment('reported|verified|approved|in_repair|repaired|unrepairable|rejected');
            $table->foreignId('reported_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('reported_at');
            $table->boolean('is_under_warranty')->default(false)->comment('Warranty status when the damage was reported');

            $table->string('repair_type', 20)->nullable()->comment('internal|vendor');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('estimated_cost', 18, 2)->default(0);
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('started_at')->nullable();
            // The movement that took the asset out of use; its origin is where the asset returns.
            $table->foreignId('repair_movement_id')->nullable()->constrained('asset_movements')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('actual_cost', 18, 2)->nullable();
            $table->text('resolution')->nullable();

            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_tickets');
    }
};
