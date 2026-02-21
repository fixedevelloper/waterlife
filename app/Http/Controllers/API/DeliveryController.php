<?php


namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Resources\DeliveryResource;
use Illuminate\Http\Request;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class DeliveryController extends Controller
{
    // Lister livraisons
    public function index(Request $request)
    {
        // Récupérer l'agent connecté
        $agent = Auth::user(); // ou Auth::user()->id selon ton modèle

        // Pagination
        $perPage = $request->input('per_page', 10); // nombre par page, 10 par défaut
        $page = $request->input('page', 1);

        // Récupérer les livraisons assignées à l'agent
        $deliveries = Delivery::with(['order.items.product', 'items'])
            ->where('delivery_agent_id', $agent->agent->id)
            ->orderByDesc('assigned_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return Helpers::success([
            'data' => DeliveryResource::collection($deliveries),
            'current_page' => $deliveries->currentPage(),
            'last_page' => $deliveries->lastPage(),
            'total' => $deliveries->total(),
            'per_page' => $deliveries->perPage()
        ]);
    }

    public function lastDeliveries()
    {
        $deliveries = Delivery::with(['order.items.product','items'])
            ->orderByDesc('assigned_at') // Les plus récentes
            ->limit(5)
            ->get();

        return Helpers::success($deliveries);
    }
    // Assigner livreur
    public function assign(Request $request)
    {
        $request->validate([
            'order_id'=>'required|exists:orders,id',
            'delivery_agent_id'=>'required|exists:agents,id'
        ]);

        $delivery = Delivery::create([
            'order_id'=>$request->order_id,
            'delivery_agent_id'=>$request->delivery_agent_id,
            'status'=>'assigned'
        ]);

        // Mettre à jour la commande
        $order = Order::find($request->order_id);
        $order->update(['delivery_status'=>'assigned','status'=>'delivery_assigned']);

        return response()->json($delivery);
    }

    // Marquer livraison terminée
    public function complete(Request $request, Delivery $delivery)
    {
        $request->validate([
            'items'=>'required|array|min:1', // array de {product_id, quantity_delivered}
            'delivery_proof_type'=>'nullable|in:otp,photo,signature',
            'delivery_proof_value'=>'nullable|string'
        ]);

        foreach($request->items as $item){
            DeliveryItem::updateOrCreate(
                ['delivery_id'=>$delivery->id,'product_id'=>$item['product_id']],
                ['quantity_delivered'=>$item['quantity_delivered']]
            );
        }

        $delivery->update([
            'status'=>'delivered',
            'delivered_at'=>now(),
            'delivery_proof_type'=>$request->delivery_proof_type,
            'delivery_proof_value'=>$request->delivery_proof_value
        ]);

        // Mettre à jour la commande
        $order = $delivery->order;
        $order->update(['delivery_status'=>'delivered','status'=>'delivered','delivered_at'=>now()]);

        return response()->json($delivery->load('items'));
    }
    public function show($id)
    {
        $delivery = Delivery::with([
            'order.items.product',
            'items.product',
            'agent'
        ])->findOrFail($id);

        return new DeliveryResource($delivery);
    }
}
