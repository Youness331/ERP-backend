<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Plain column + index instead of a constraint so the same migration
            // runs unchanged on MySQL and SQLite (SQLite cannot add FK constraints
            // through ALTER TABLE).
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role_id', 'phone', 'job_title', 'is_active', 'last_login_at']);
        });
    }
};
