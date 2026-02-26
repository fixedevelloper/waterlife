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
        Schema::create('managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('forage_name')->nullable();

            $table->decimal('balance', 12, 2)->default(0); // 🔥 clé du système

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'agent', 'manager', 'admin'])->change()->default('customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('managers');
    }
};
