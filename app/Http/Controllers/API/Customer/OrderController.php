<?php


namespace App\Http\Controllers\API\Customer;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Helpers\ResponseHelper;
use App\Http\Resources\OrderMiniResource;
use App\Http\Resources\OrderResource;
use App\Models\Address;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class OrderController extends Controller
{
    // Lister toutes les commandes
    public function recentOrders()
    {
        $orders = Order::with([
            'customer.user',
            'collector.user',
            'deliveryAgent.user',
            'address',
            'zone',
            'items.product'
        ])
            ->where('customer_id', auth()->user()->customer->id)
            ->latest()
            ->take(5)
            ->get(); // ✅ IMPORTANT

        return Helpers::success(
            OrderResource::collection($orders)
        );
    }

    public function statusOrders(Request $request)
    {
        $status = $request->get('status');

        $query = Order::with([
            'customer.user',
            'collector.user',
            'deliveryAgent.user',
            'address',
            'zone',
            'items.product'
        ]);

        // 🔥 Filtre dynamique
        if (!empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query
            ->latest()
            ->get(); // ✅ sans pagination

        return Helpers::success(
            OrderResource::collection($orders)
        );
    }
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Order::with([
            'customer.user',
            'collector.user',
            'deliveryAgent.user',
            'address',
            'zone',
            'items.product'
        ]);

        /*
        |--------------------------------------------------------------------------
        | 🔐 Filtrage par rôle
        |--------------------------------------------------------------------------
        */
        if (in_array($user->role, ['agent', 'customer']) && $user->customer) {
            $query->where('customer_id', $user->customer->id);
        }

        /*
        |--------------------------------------------------------------------------
        | 🔎 FILTRES OPTIONNELS
        |--------------------------------------------------------------------------
        */

        // 🔹 Filtre par status
        if ($request->filled('status') && $request->status !== 'all') {
            $statuses = explode(',', $request->status); // ex: status=processing,on_route
            $query->whereIn('status', $statuses);
        }
        // 🔹 Filtre par date (yyyy-mm-dd)
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // 🔹 Filtre par zone
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        $orders = $query
            ->latest()
            ->paginate($request->get('per_page', 10));

        return ResponseHelper::success(OrderResource::collection($orders),'liste commandes');
    }

    // Créer une nouvelle commande
    public function store(Request $request)
    {
        $request->validate([
            'payment_method'=>'required|string',
            'address_id' => 'required|exists:addresses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = auth()->user();
        $customer = $user->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $address = Address::where('id', $request->address_id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        DB::beginTransaction();

        try {

            $subtotal = 0;

            $order = Order::create([
                'order_number' => Str::upper(Str::random(8)),
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'zone_id' => $address->zone_id,
                'subtotal' => 0,
                'delivery_fee' => 0,
                'total_amount' => 0,
                'collection_status' => 'pending',
                'delivery_status' => 'pending',
                'status' => 'pending',
                'scheduled_at' => now()->addHour()
            ]);

            foreach ($request->items as $item) {

                $product = Product::findOrFail($item['product_id']);

                $totalPrice = $product->base_price * $item['quantity'];
                $subtotal += $totalPrice;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->base_price,
                    'total_price' => $totalPrice
                ]);
            }

            // Exemple calcul livraison simple par zone
            $deliveryFee = $address->zone->delivery_fee ?? 0;

            $order->update([
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $subtotal + $deliveryFee
            ]);

            $payment = Payment::create([
                'order_id' => $order->id, // 🔥 IMPORTANT
                'method' => $request->payment_method,
                'transaction_reference' => Str::uuid(), // 🔥 unique
                'amount' => $order->total_amount,
                'status' => 'pending',
            ]);
            DB::commit();

            return Helpers::success($order);

        } catch (\Exception $e) {

            DB::rollBack();

            logger($e->getMessage());
            return Helpers::error('Erreur lors de la création', $e->getMessage());

        }
    }

    public function generatePaymentLink($id, Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:9',
            'operator' => 'required|in:MTN,ORANGE'
        ]);


        DB::beginTransaction();

        try {

            $order=Order::find($id);
            $payment = $order->payment;
         //   logger($order);
            logger($payment);
            if (!$payment) {
                return Helpers::error('Paiement introuvable');
            }

            // 🔥 Générer token sécurisé
            $token = Str::uuid();

            $payment->update([
                'phone' => $request->phone,
                'operator' => $request->operator,
                'status' => 'pending',
                'token' => $token
            ]);

            DB::commit();

            return Helpers::success([
                'payment_url' => url("/pay/{$order->id}?token={$token}")
            ]);

        } catch (\Exception $e) {

            DB::rollBack();
            logger($e->getMessage());
            return Helpers::error('Erreur lors de la génération du paiement');
        }
    }

// Voir détails d'une commande
    public function show(Order $order)
    {
        $order->load([
            'items.product',
            'customer.user',
            'collector.user',
            'deliveryAgent.user',
            'collect.items',
            'delivery.items'
        ]);

        return new OrderMiniResource($order);
    }
    public function showByOrderNumber($orderNumber)
    {
        $order = Order::query()->where('id', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'message' => 'Commande introuvable'
            ], 404);
        }

        return new OrderResource($order);
    }

    public function updateByNumber(string $orderNumber, string $status)
    {
        logger($orderNumber);
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'message' => 'Commande introuvable'
            ], 404);
        }

        // Optionnel : Valider que le statut est correct
        $allowedStatuses = [
            'pending',
            'collector_assigned',
            'processing',
            'delivery_assigned',
            'delivered',
            'cancelled'];
        if (!in_array($status, $allowedStatuses)) {
            return response()->json([
                'message' => 'Statut invalide'
            ], 400);
        }

        $order->update([
            'status' => $status
        ]);

        return new OrderResource($order);
    }
    // Mettre à jour le statut
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status'=>'required|in:pending,collector_assigned,processing,delivery_assigned,delivered,cancelled'
        ]);

        $order->update([
            'status'=>$request->status
        ]);

        return response()->json($order);
    }
    public function preview(Request $request)
    {
        $request->validate([
            'address_id' => 'required|exists:addresses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        $address = Address::where('id', $request->address_id)
            ->where('customer_id', $customer->id)
            ->with('zone')
            ->firstOrFail();

        $subtotal = 0;

        foreach ($request->items as $item) {

            $product = Product::findOrFail($item['product_id']);

            $subtotal += $product->base_price * $item['quantity'];
        }

        // 🔹 Exemple calcul distance simple (à remplacer par Google Maps)
        $warehouseLat = config('app.warehouse_lat');
        $warehouseLng = config('app.warehouse_lng');

        $distanceKm = $this->calculateDistance(
            $warehouseLat,
            $warehouseLng,
            $address->latitude,
            $address->longitude
        );

        // 🔹 Exemple calcul livraison par zone
        $deliveryFee = $address->zone->delivery_fee ?? 0;

        // 🔹 Exemple surcharge si distance > 10km
        if ($distanceKm > 10) {
            $deliveryFee += 1000;
        }

        $total = $subtotal + $deliveryFee;

        return Helpers::success([
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'distance_km' => round($distanceKm, 2),
            'total' => $total,
            'currency' => 'FCFA'
        ]);
    }
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

}
