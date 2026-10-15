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
            $table->string('source_key', 64)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['user_id', 'source_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbound_receipts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'source_key']);
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropColumn('source_key');
        });
    }
};
