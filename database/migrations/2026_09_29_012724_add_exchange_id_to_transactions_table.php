<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('exchange_id')->nullable()->constrained('currency_exchanges')->cascadeOnDelete();
            $table->unique(['exchange_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['exchange_id']);
            $table->dropUnique(['exchange_id', 'type']);
            $table->dropColumn('exchange_id');
        });
    }
};
