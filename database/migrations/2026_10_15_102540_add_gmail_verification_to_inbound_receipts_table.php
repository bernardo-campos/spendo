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
            $table->string('gmail_forwarding_source')->nullable();
            $table->text('gmail_verification_url')->nullable();
            $table->timestamp('gmail_verification_dismissed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbound_receipts', function (Blueprint $table) {
            $table->dropColumn(['gmail_forwarding_source', 'gmail_verification_url', 'gmail_verification_dismissed_at']);
        });
    }
};
