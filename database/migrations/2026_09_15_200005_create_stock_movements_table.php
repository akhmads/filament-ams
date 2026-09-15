<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The stock card: an append-only ledger of every change to an item's stock in
     * a warehouse, with the running balance after each change.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_document_line_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 20)->comment('receipt|issue|transfer_out|transfer_in|adjustment|count');
            $table->date('moved_on');
            $table->decimal('quantity', 18, 2)->comment('Signed: positive into the warehouse, negative out');
            $table->decimal('value', 18, 2)->comment('Signed stock value moved');
            $table->decimal('balance_quantity', 18, 2);
            $table->decimal('balance_value', 18, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['stock_item_id', 'location_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
