<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consumables and spare parts, counted by quantity rather than tracked unit by unit.
     */
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('item_type', 20)->default('consumable')->comment('consumable|spare_part');
            $table->string('unit', 20)->comment('Unit of measure, e.g. pcs, box, litre');
            $table->decimal('minimum_quantity', 18, 2)->default(0)->comment('Alert when stock across all warehouses falls below this');
            $table->decimal('reorder_quantity', 18, 2)->nullable()->comment('Usual quantity to buy when restocking');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
