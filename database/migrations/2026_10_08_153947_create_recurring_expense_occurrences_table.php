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
        Schema::create('recurring_expense_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->string('status');
            $table->timestamps();
            $table->unique(['recurring_expense_id', 'period']);
            $table->unique('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_occurrences');
    }
};
