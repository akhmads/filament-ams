<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('width_mm', 6, 2)->default(50);
            $table->decimal('height_mm', 6, 2)->default(25);
            $table->unsignedTinyInteger('columns')->default(1)->comment('Labels per row when printing on sheet stock');
            $table->decimal('margin_mm', 5, 2)->default(2);
            $table->decimal('font_size_pt', 5, 2)->default(6);
            $table->string('code_type', 20)->default('qr')->comment('qr|barcode|both');
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_company_name')->default(true);
            $table->boolean('show_asset_name')->default(true);
            $table->boolean('show_branch')->default(false);
            $table->boolean('show_acquisition_date')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
