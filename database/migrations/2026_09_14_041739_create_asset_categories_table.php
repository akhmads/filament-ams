<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->unsignedInteger('_lft')->default(0);
            $table->unsignedInteger('_rgt')->default(0);
            $table->string('code', 20)->unique();
            $table->string('prefix', 10)->comment('Asset code segment, e.g. LAP for laptops');
            $table->string('name');
            $table->text('description')->nullable();

            // Depreciation defaults inherited by new assets (used fully in Phase 2)
            $table->string('depreciation_method', 30)->default('straight_line');
            $table->unsignedSmallInteger('useful_life_months')->default(48);
            $table->decimal('residual_percent', 5, 2)->default(0);

            $table->boolean('is_depreciable')->default(true);
            $table->boolean('requires_maintenance')->default(false);
            $table->json('spec_fields')->nullable()->comment('Definition of the dynamic specification fields for this category');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['_lft', '_rgt', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
