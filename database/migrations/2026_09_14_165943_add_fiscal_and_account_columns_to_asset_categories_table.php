<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->string('fiscal_group', 30)->nullable()->after('residual_percent')
                ->comment('Tax asset group under UU PPh Pasal 11 ayat 6');
            $table->string('fiscal_method', 30)->default('straight_line')->after('fiscal_group');
            $table->string('expense_account_code', 30)->nullable()->after('fiscal_method');
            $table->string('expense_account_name')->nullable()->after('expense_account_code');
            $table->string('accumulated_account_code', 30)->nullable()->after('expense_account_name');
            $table->string('accumulated_account_name')->nullable()->after('accumulated_account_code');
        });
    }

    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_group', 'fiscal_method',
                'expense_account_code', 'expense_account_name',
                'accumulated_account_code', 'accumulated_account_name',
            ]);
        });
    }
};
