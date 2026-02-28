<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {

        // -----------------------------
        // ZONES
        // -----------------------------
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // -----------------------------
        // AGENTS
        // -----------------------------
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_collect')->default(true);
            $table->boolean('can_deliver')->default(true);
            $table->boolean('is_available')->default(true);
            $table->string('vehicle_type')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
            $table->timestamps();
        });

        // -----------------------------
        // CUSTOMERS
        // -----------------------------
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->timestamps();
        });

        // -----------------------------
        // ADDRESSES
        // -----------------------------
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('map_label')->nullable();
            $table->boolean('is_default')->default(false);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // -----------------------------
        // PRODUCTS (bidons)
        // -----------------------------
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Bidon 5L", "Bidon 10L", ...
            $table->integer('volume_liters');
            $table->decimal('base_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // -----------------------------
        // ORDERS
        // -----------------------------
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('delivery_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('address_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('total_amount', 10, 2);

            $table->enum('collection_status', ['pending','assigned','collected'])->default('pending');
            $table->enum('delivery_status', ['pending','assigned','on_route','delivered'])->default('pending');
            $table->enum('status', [
                'pending',
                'collector_assigned',
                'processing',
                'delivery_assigned',
                'delivered',
                'cancelled'
            ])->default('pending');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->decimal('platform_margin', 10, 2)->default(0);
            $table->timestamps();
        });

        // -----------------------------
        // ORDER ITEMS (bidons par volume)
        // -----------------------------
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
        });

        // -----------------------------
        // COLLECTS (bidons vides)
        // -----------------------------
        Schema::create('collects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('agents')->cascadeOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->enum('status',['assigned','on_route','collected','cancelled'])->default('assigned');
            $table->string('collection_image')->nullable();
            $table->timestamps();
        });

        // -----------------------------
        // COLLECT ITEMS (quantités par volume)
        // -----------------------------
        Schema::create('collect_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_ordered')->default(0);
            $table->integer('quantity_collected')->default(0);
            $table->timestamps();
        });

        // -----------------------------
        // DELIVERIES (bidons pleins)
        // -----------------------------
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_agent_id')->constrained('agents')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->enum('status',['assigned','on_route','delivered'])->default('assigned');
            $table->enum('delivery_proof_type',['otp','photo','signature'])->nullable();
            $table->string('delivery_proof_value')->nullable();
            $table->string('delivery_image')->nullable();
            $table->timestamps();
        });

        // -----------------------------
        // DELIVERY ITEMS (quantités par volume)
        // -----------------------------
        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_collected')->default(0);
            $table->integer('quantity_delivered')->default(0);
            $table->timestamps();
        });

        // -----------------------------
        // PAYMENTS
        // -----------------------------
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('method',['cash','mobile_money']);
            $table->string('transaction_reference')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status',['pending','success','failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // -----------------------------
        // CONTAINER TRANSACTIONS
        // -----------------------------
        Schema::create('container_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->enum('type',['collected_from_customer','returned_to_customer','damaged','lost']);
            $table->timestamps();
        });
        Schema::create('managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('forage_name')->nullable();

            $table->decimal('balance', 12, 2)->default(0); // 🔥 clé du système

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
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

    public function down(): void
    {
        Schema::dropIfExists('container_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('collect_items');
        Schema::dropIfExists('collects');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('users');
    }
};
