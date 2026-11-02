<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('reserved_quantity', 15, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->decimal('quantity', 15, 3);
            $table->decimal('quantity_before', 15, 3)->default(0);
            $table->decimal('quantity_after', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['reference_type', 'reference_id']);
            $table->index(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
};
