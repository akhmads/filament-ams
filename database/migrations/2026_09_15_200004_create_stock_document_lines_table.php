<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 2)->comment('Moved quantity; the signed change on an adjustment; the counted quantity on a stock count');
            $table->decimal('unit_cost', 18, 2)->nullable()->comment('Purchase price per unit on a receipt');
            $table->string('notes')->nullable();
            $table->decimal('system_quantity', 18, 2)->nullable()->comment('Stock on hand when a count was posted');
            $table->decimal('posted_value', 18, 2)->nullable()->comment('Stock value the line moved when posted; signed on adjustments and counts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_document_lines');
    }
};
