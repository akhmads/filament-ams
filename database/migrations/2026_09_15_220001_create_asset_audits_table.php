<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A physical count of assets in a location or department, from the list of
     * assets it expects to find to the corrections made afterwards.
     */
    public function up(): void
    {
        Schema::create('asset_audits', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('title');
            $table->string('status', 20)->default('in_progress')->comment('in_progress|under_review|completed|cancelled');
            $table->foreignId('location_id')->nullable()->comment('Includes every location under it')
                ->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('asset_category_id')->nullable()->comment('Includes every subcategory')
                ->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('counted_at')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_audits');
    }
};
