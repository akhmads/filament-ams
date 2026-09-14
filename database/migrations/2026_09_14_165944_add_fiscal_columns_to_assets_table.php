<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('fiscal_group', 30)->nullable()->after('depreciation_start_date')
                ->comment('Tax asset group; falls back to the category when empty');
            $table->string('fiscal_method', 30)->default('straight_line')->after('fiscal_group');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['fiscal_group', 'fiscal_method']);
        });
    }
};
