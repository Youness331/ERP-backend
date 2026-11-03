<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 60)->nullable();
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->string('direction', 10)->default('in');
            $table->date('value_date');
            $table->string('status', 20)->default('unmatched')->index();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('value_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
