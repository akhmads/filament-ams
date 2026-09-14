<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per book per month. A draft can be recalculated freely; once
     * posted the period and its entries are locked.
     */
    public function up(): void
    {
        Schema::create('depreciation_periods', function (Blueprint $table) {
            $table->id();
            $table->string('book', 20)->comment('commercial|fiscal');
            $table->date('period')->comment('First day of the month');
            $table->string('status', 20)->default('draft')->comment('draft|posted');
            $table->unsignedInteger('asset_count')->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['book', 'period']);
            $table->index(['book', 'status', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_periods');
    }
};
