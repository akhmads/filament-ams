<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            // Recalculating a draft replaces its entries, hence the cascade.
            $table->foreignId('depreciation_period_id')->constrained()->cascadeOnDelete();
            // Entries must outlive nothing: an asset with history cannot be hard-deleted.
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('book', 20);
            $table->date('period');
            $table->decimal('opening_book_value', 18, 2);
            $table->decimal('amount', 18, 2);
            $table->decimal('accumulated', 18, 2);
            $table->decimal('closing_book_value', 18, 2);
            $table->boolean('is_final')->default(false)->comment('Last month of the asset schedule');
            $table->timestamps();

            $table->unique(['asset_id', 'book', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
    }
};
