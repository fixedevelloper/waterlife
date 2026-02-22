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
use Illuminate\Support\Facades\Storage;

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
    public function complete2(Request $request, Delivery $delivery)
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
    public function complete(Request $request, $deliveryId)
    {
        // 🔹 Si 'items' est passé en JSON string, le décoder
        if (is_string($request->items)) {
            $request->merge([
                'items' => json_decode($request->items, true)
            ]);
        }

        try {


            logger($request->all());

            // 🔹 Validation
            $data = $request->validate([
                'items' => 'required|array',
                'items.*.item_id' => 'required|exists:delivery_items,id',
                'items.*.quantity_delivered' => 'required|integer|min:0',
                'delivery_proof_type' => 'nullable|in:otp,photo,signature',
                'otp' => 'nullable|string',
                'delivery_image' => 'nullable|image|max:2048',
                'signature' => 'nullable|string', // base64
            ]);

            logger($data);

            $delivery = Delivery::findOrFail($deliveryId);

            // 🔄 Mise à jour des items
            foreach ($data['items'] as $item) {
                DeliveryItem::where('id', $item['item_id'])
                    ->update([
                        'quantity_delivered' => $item['quantity_delivered']
                    ]);
            }

            // 📸 Traitement image
            $imagePath = null;
            if ($request->hasFile('delivery_image')) {
                $imagePath = $request->file('delivery_image')->store('deliveries', 'public');
            }

            // ✍️ Traitement signature base64
            $signaturePath = null;
            if (!empty($data['signature'])) {
                $signatureData = str_replace(['data:image/png;base64,', ' '], ['', '+'], $data['signature']);
                $fileName = 'signature_' . time() . '.png';
                Storage::disk('public')->put("signatures/$fileName", base64_decode($signatureData));
                $signaturePath = "signatures/$fileName";
            }

            // 🔹 Définir la valeur du proof en fonction du type choisi (sécurisé)
            $proofValue = null;
            switch ($data['delivery_proof_type'] ?? '') {
                case 'photo':
                    $proofValue = $imagePath;
                    break;
                case 'signature':
                    $proofValue = $signaturePath;
                    break;
                case 'otp':
                default:
                    $proofValue = null;
                    break;
            }

            // ✅ Finalisation de la livraison
            $delivery->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'delivery_proof_type' => $data['delivery_proof_type'],
                'delivery_proof_value' => $proofValue
            ]);

            return response()->json([
                'message' => 'Livraison validée avec succès'
            ]);
        } catch (\Exception $exception) {
            logger($exception->getMessage());
            return Helpers::error('erreur de livraison');

        }
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
