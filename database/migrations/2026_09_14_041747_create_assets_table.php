<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Used in the QR URL so the id is not easily guessed');
            $table->string('code', 40)->unique()->comment('Permanent asset code, never changes');

            $table->string('name');
            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_model_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('assets')->nullOnDelete()->comment('Parent, when this asset is a component');

            $table->string('serial_number')->nullable();
            $table->year('manufacture_year')->nullable();

            // Acquisition
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 18, 2)->default(0);
            $table->string('po_number', 50)->nullable();
            $table->string('invoice_number', 50)->nullable();
            $table->string('funding_source', 50)->nullable();

            // Depreciation (seeded from the category defaults, used fully in Phase 2)
            $table->boolean('is_depreciable')->default(true);
            $table->string('depreciation_method', 30)->default('straight_line');
            $table->unsignedSmallInteger('useful_life_months')->default(48);
            $table->decimal('residual_value', 18, 2)->default(0);
            $table->date('depreciation_start_date')->nullable();

            // Warranty
            $table->date('warranty_start')->nullable();
            $table->date('warranty_end')->nullable();
            $table->string('warranty_vendor')->nullable();

            // Current position: a cache of the latest asset_movements row
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('placement_type', 20)->default('warehouse')->comment('employee|location|warehouse|in_transit|vendor');
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('current_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->string('status', 30)->default('available');
            $table->string('condition', 30)->default('good');

            $table->json('specs')->nullable()->comment('Values of the dynamic specification fields for the category');
            $table->text('notes')->nullable();

            $table->unsignedInteger('label_printed_count')->default(0);
            $table->timestamp('label_printed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['asset_category_id', 'status']);
            $table->index(['placement_type', 'current_employee_id']);
            $table->index(['placement_type', 'current_location_id']);
            $table->index('serial_number');
            $table->index('warranty_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
