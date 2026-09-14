<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('condition', 30)->default('good')->comment('Condition of the asset when this document was raised');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['asset_assignment_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignment_items');
    }
};
