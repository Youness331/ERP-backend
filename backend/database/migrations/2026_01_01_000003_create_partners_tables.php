<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('company_name', 150)->nullable();
            $table->string('email', 150)->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('Maroc');
            $table->string('ice', 20)->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('rc', 20)->nullable();
            $table->string('patente', 20)->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('company_name', 150)->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('email', 150)->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('Maroc');
            $table->string('ice', 20)->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('rc', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('clients');
    }
};
