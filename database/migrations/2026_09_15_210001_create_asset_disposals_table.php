<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A proposal to write assets off the books, from its approval to the disposal
     * itself. One document can cover many assets, as at an auction.
     */
    public function up(): void
    {
        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('status', 20)->default('proposed')->comment('proposed|approved|rejected|completed|cancelled');
            $table->date('disposal_date');
            $table->string('recipient_name')->nullable()->comment('Buyer, or the recipient of a donation');
            $table->string('reference_number', 100)->nullable()->comment('Auction, sale invoice or donation letter number');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'disposal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
