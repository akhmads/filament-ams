<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A recurring preventive maintenance plan, for one asset or for every asset
     * in a category. Due dates are not stored: each asset's next due date follows
     * from its latest work order under the plan.
     */
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Exactly one target is filled in.
            $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('asset_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('include_subcategories')->default(true)->comment('Category plans also cover assets in child categories');

            $table->unsignedSmallInteger('interval_value');
            $table->string('interval_unit', 10)->comment('day|week|month|year');
            $table->date('start_date')->comment('First due date for assets acquired on or before it');
            $table->unsignedSmallInteger('lead_days')->default(7)->comment('Work orders open this many days before they are due');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete()->comment('Service vendor, when the work is outsourced');
            $table->decimal('estimated_cost', 18, 2)->default(0);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->json('checklist')->nullable()->comment('Tasks copied onto each work order');

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};
