<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_positions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['assign', 'revoke', 'transfer'])->default('assign');
            $table->foreignId('asset_id')->index()->default(0);
            $table->string('assignable_type')->index();
            $table->string('assignable_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_positions');
    }
};
