<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock on hand and its value per item per warehouse — a cache of the
     * stock movement ledger, updated under a row lock when a document is posted.
     */
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 2)->default(0);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['stock_item_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
