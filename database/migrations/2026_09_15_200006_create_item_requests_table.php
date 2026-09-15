<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An employee's request for consumables, recorded by staff, approved, then
     * issued from a warehouse.
     */
    public function up(): void
    {
        Schema::create('item_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('status', 20)->default('submitted')->comment('submitted|approved|rejected|fulfilled|cancelled');
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('request_date');
            $table->date('needed_by')->nullable();
            $table->text('purpose')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('fulfilled_at')->nullable();
            $table->foreignId('stock_document_id')->nullable()->comment('The goods issue that fulfilled the request')
                ->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'request_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_requests');
    }
};
