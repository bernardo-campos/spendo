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
        Schema::table('inbound_receipts', function (Blueprint $table) {
            $table->string('currency', 3)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('origin_address')->nullable();
            $table->text('destination_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbound_receipts', function (Blueprint $table) {
            $table->dropColumn(['currency', 'amount', 'origin_address', 'destination_address']);
        });
    }
};
