<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->comment('asset|assignment|etc');
            $table->string('scope', 60)->default('')->comment('Sequence scope, e.g. IT-LAP-HO-2609');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['key', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
