<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maintenance work on one asset, opened by a plan or by hand.
     */
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->date('due_date');
            $table->string('status', 20)->default('open')->comment('open|in_progress|completed|cancelled');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('estimated_cost', 18, 2)->default(0);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->text('instructions')->nullable();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 20)->nullable()->comment('ok|needs_follow_up');
            $table->decimal('actual_cost', 18, 2)->nullable();
            $table->unsignedInteger('labor_minutes')->nullable();
            $table->text('findings')->nullable();

            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // A plan opens at most one work order per asset per due date, even when
            // the scheduler and a manual run overlap.
            $table->unique(['maintenance_plan_id', 'asset_id', 'due_date']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
