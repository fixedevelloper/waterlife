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
        Schema::create('versements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('manager_id')->constrained()->cascadeOnDelete();

            // 💰 montant versé à la plateforme
            $table->decimal('amount', 12, 2);

            // 💳 type
            $table->enum('method', ['cash', 'mobile_money', 'bank']);

            // 🔗 ref paiement (MoMo / OM)
            $table->string('reference')->nullable();

            // 📱 opérateur
            $table->string('provider')->nullable();

            // 📊 statut
            $table->enum('status', ['pending', 'validated', 'rejected'])->default('pending');

            // 👤 validation
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            // 📅 période concernée (🔥 très important)
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            // 📝 note
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('versements');
    }
};
