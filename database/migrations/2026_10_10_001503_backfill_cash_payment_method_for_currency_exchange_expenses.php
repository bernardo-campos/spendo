<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('transactions')
            ->whereNotNull('exchange_id')
            ->where('type', 'expense')
            ->whereNull('payment_method')
            ->update(['payment_method' => 'cash']);
    }

    /**
     * Existing cash values cannot be distinguished from backfilled values.
     */
    public function down(): void {}
};
