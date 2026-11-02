<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->unique();
            $table->string('barcode', 60)->nullable()->index();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit', 30)->default('unité');
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(20);
            $table->decimal('reorder_level', 15, 3)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
