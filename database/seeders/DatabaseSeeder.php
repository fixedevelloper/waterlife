<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Zone;
use App\Models\Address;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Collect;
use App\Models\CollectItem;
use App\Models\Delivery;
use App\Models\DeliveryItem;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------
        // Créer une zone
        // -----------------------------
        $zone = Zone::create([
            'name' => 'Quartier Central',
            'delivery_fee' => 500,
            'is_active' => true,
        ]);

        // -----------------------------
        // Créer produits (bidons)
        // -----------------------------
        $products = [
            ['name'=>'Bidon 5L','volume_liters'=>5,'base_price'=>1000],
            ['name'=>'Bidon 10L','volume_liters'=>10,'base_price'=>1800],
            ['name'=>'Bidon 20L','volume_liters'=>20,'base_price'=>3000],
        ];

        foreach($products as $prod){
            Product::create($prod);
        }

        // -----------------------------
        // Créer clients
        // -----------------------------
        $customers = [];
        for($i=1;$i<=3;$i++){
            $user = User::create([
                'name'=>fake()->name(),
                'phone'=>"69000000$i",
                'password'=>bcrypt('password'),
                'role'=>'customer'
            ]);

            $customer = Customer::create([
                'user_id'=>$user->id,
                'full_name'=>"Client $i"
            ]);

            $address = Address::create([
                'customer_id'=>$customer->id,
                'zone_id'=>$zone->id,
                'label'=>"Maison Client $i",
                'latitude'=>3.848,
                'longitude'=>11.502,
                'description'=>"Adresse Client $i"
            ]);

           // $customer->update(['default_address_id'=>$address->id]);
            $customers[] = $customer;
        }

        // -----------------------------
        // Créer agents
        // -----------------------------
        $agents = [];
        for($i=1;$i<=2;$i++){
            $user = User::create([
                'name'=>fake()->name(),
                'phone'=>"69900000$i",
                'password'=>bcrypt('password'),
                'role'=>'agent'
            ]);

            $agent = Agent::create([
                'user_id'=>$user->id,
                'zone_id'=>$zone->id,
                'can_collect'=>true,
                'can_deliver'=>true,
                'is_available'=>true
            ]);
            $agents[] = $agent;
        }

        // -----------------------------
        // Créer une commande pour Client 1
        // -----------------------------
        $customer = $customers[0];
        $order = Order::create([
            'order_number'=>Str::upper(Str::random(8)),
            'customer_id'=>$customer->id,
            'address_id'=>$customer->addresses[0]->id,
            'zone_id'=>$zone->id,
            'subtotal'=>0,
            'delivery_fee'=>$zone->delivery_fee,
            'total_amount'=>0,
            'collection_status'=>'pending',
            'delivery_status'=>'pending',
            'status'=>'pending',
            'scheduled_at'=>Carbon::now()->addHour()
        ]);

        // -----------------------------
        // Ajouter les bidons à la commande
        // -----------------------------
        $orderItemsData = [
            ['product_id'=>Product::where('volume_liters',5)->first()->id,'quantity'=>2],
            ['product_id'=>Product::where('volume_liters',10)->first()->id,'quantity'=>1],
            ['product_id'=>Product::where('volume_liters',20)->first()->id,'quantity'=>1],
        ];

        $subtotal = 0;
        foreach($orderItemsData as $itemData){
            $product = Product::find($itemData['product_id']);
            $total_price = $product->base_price * $itemData['quantity'];
            $subtotal += $total_price;

            OrderItem::create([
                'order_id'=>$order->id,
                'product_id'=>$product->id,
                'quantity'=>$itemData['quantity'],
                'unit_price'=>$product->base_price,
                'total_price'=>$total_price
            ]);
        }

        $order->update([
            'subtotal'=>$subtotal,
            'total_amount'=>$subtotal + $zone->delivery_fee
        ]);

        // -----------------------------
        // Assigner un collecteur
        // -----------------------------
        $collector = $agents[0]; // Agent 1
        $collect = Collect::create([
            'order_id'=>$order->id,
            'collector_id'=>$collector->id,
            'status'=>'assigned'
        ]);

        foreach($orderItemsData as $itemData){
            CollectItem::create([
                'collect_id'=>$collect->id,
                'product_id'=>$itemData['product_id'],
                'quantity_collected'=>$itemData['quantity']
            ]);
        }

        $order->update([
            'collection_status'=>'collected',
            'status'=>'processing'
        ]);

        // -----------------------------
        // Assigner un livreur (peut être même autre agent)
        // -----------------------------
        $deliveryAgent = $agents[1]; // Agent 2
        $delivery = Delivery::create([
            'order_id'=>$order->id,
            'delivery_agent_id'=>$deliveryAgent->id,
            'status'=>'assigned'
        ]);

        foreach($orderItemsData as $itemData){
            DeliveryItem::create([
                'delivery_id'=>$delivery->id,
                'product_id'=>$itemData['product_id'],
                'quantity_delivered'=>$itemData['quantity']
            ]);
        }

        $order->update([
            'delivery_status'=>'delivered',
            'status'=>'delivered',
            'delivered_at'=>Carbon::now()
        ]);

    }
}
