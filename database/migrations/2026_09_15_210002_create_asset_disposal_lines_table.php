<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One asset on a disposal. The book values and gain or loss are fixed when the
     * disposal is completed, from the depreciation posted up to the month before.
     */
    public function up(): void
    {
        Schema::create('asset_disposal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_disposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('repair_ticket_id')->nullable()->comment('The repair that found the asset could not be repaired')
                ->constrained()->nullOnDelete();
            $table->string('method', 20)->comment('sale|trade_in|donation|scrapped|lost');
            $table->decimal('proceeds', 18, 2)->default(0)->comment('Sale price or trade-in value');
            $table->string('notes')->nullable();

            $table->decimal('acquisition_cost', 18, 2)->nullable();
            $table->decimal('commercial_accumulated', 18, 2)->nullable();
            $table->decimal('commercial_book_value', 18, 2)->nullable();
            $table->decimal('commercial_gain_loss', 18, 2)->nullable();
            $table->decimal('fiscal_accumulated', 18, 2)->nullable();
            $table->decimal('fiscal_book_value', 18, 2)->nullable();
            $table->decimal('fiscal_gain_loss', 18, 2)->nullable();
            $table->foreignId('disposal_movement_id')->nullable()->comment('The ledger movement that wrote the asset off')
                ->constrained('asset_movements')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposal_lines');
    }
};
