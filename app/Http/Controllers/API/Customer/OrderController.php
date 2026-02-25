<?php


namespace App\Http\Controllers\API\Customer;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Resources\OrderMiniResource;
use App\Http\Resources\OrderResource;
use App\Models\Address;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

    public function indexAdmin()
    {
        $orders = Order::with([
            'customer.user',
            'collector.user',
            'deliveryAgent.user',
            'address',
            'zone',
            'items.product'
        ])
            ->latest()
            ->paginate(10);

        return Helpers::success(
             OrderResource::collection($orders));
    }
    public function index()
    {
        $orders = Order::with([
            'customer.user',
            'collector.user',
            'deliveryAgent.user',
            'address',
            'zone',
            'items.product'
        ])
            ->where('customer_id', auth()->user()->agen->id)
            ->latest()
            ->paginate(10);

        return Helpers::success([
            'data' => OrderResource::collection($orders),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
            'per_page' => $orders->perPage()
        ]);
    }

    // Créer une nouvelle commande
    public function store(Request $request)
    {
        $request->validate([
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

            DB::commit();

            return response()->json(
                $order->load('items.product', 'address', 'customer'),
                201
            );

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la création',
                'error' => $e->getMessage()
            ], 500);
        }
    }


// Voir détails d'une commande
    public function show(Order $order)
    {
        // Sécurité : vérifier que la commande appartient au client connecté
  /*      if ($order->customer_id !== auth()->user()->customer->id) {
            return Helpers::error('Unauthorized', 403);
        }*/

        $order->load([
            'items.product',
            'customer.user',
            'collector.user',
            'deliveryAgent.user',
            'collects.items',
            'deliveries.items'
        ]);

        return new OrderMiniResource($order);
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
