<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receipts, issues, transfers, adjustments and stock counts. A draft changes
     * nothing; posting writes the stock movements and locks the document.
     */
    public function up(): void
    {
        Schema::create('stock_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('type', 20)->comment('receipt|issue|transfer|adjustment|count');
            $table->string('status', 20)->default('draft')->comment('draft|posted');
            $table->date('document_date');
            $table->foreignId('location_id')->comment('The warehouse the document acts on; where a transfer comes from')
                ->constrained()->restrictOnDelete();
            $table->foreignId('destination_location_id')->nullable()->comment('Where a transfer goes')
                ->constrained('locations')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number', 100)->nullable()->comment('Delivery note or invoice number');
            $table->foreignId('employee_id')->nullable()->comment('Who received the issued goods')
                ->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            // The work order, repair ticket or item request the goods were issued for.
            $table->nullableMorphs('source');
            $table->text('notes')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status', 'document_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_documents');
    }
};
