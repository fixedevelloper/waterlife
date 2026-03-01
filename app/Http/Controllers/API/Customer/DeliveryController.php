<?php


namespace App\Http\Controllers\API\Customer;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Helpers\OrderStatus;
use App\Http\Helpers\ResponseHelper;
use App\Http\Resources\CollectResource;
use App\Http\Resources\DeliveryResource;
use Illuminate\Http\Request;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        return ResponseHelper::success(
            DeliveryResource::collection($deliveries),
            'Liste des livraisons paginée'
        );
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
    public function assignDelivery(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->agent) {
            return Helpers::validation('Non autorisé', 403);
        }

        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        try {
            $delivery = DB::transaction(function () use ($request, $user) {
                // 🔒 Lock pour éviter double assign
                $order = Order::lockForUpdate()
                    ->with(['items', 'collect.items', 'delivery'])
                    ->findOrFail($request->order_id);

                // 🔴 Vérifier statut commande
                if (in_array($order->status, ['delivered', 'cancelled'])) {
                    return Helpers::validation('Impossible d’assigner une livraison à cette commande');
                }

                // 🔴 Vérifier collecte
                if (!$order->collect) {
                    return Helpers::validation('La collecte doit être effectuée avant la livraison');
                }

                $existingDelivery = $order->delivery;

                // ✅ Si une livraison existe déjà
                if ($existingDelivery) {
                    if ($existingDelivery->delivery_agent_id == $user->agent->id) {
                        $existingDelivery->update(['status' => 'assigned']);
                        return $existingDelivery;
                    }

                    // Autre agent → annuler l’ancienne livraison
                    $existingDelivery->update(['status' => 'cancelled']);
                }

                // 🔹 Créer la livraison pour cette commande
                $delivery = Delivery::create([
                    'order_id' => $order->id,
                    'delivery_agent_id' => $user->agent->id,
                    'status' => 'assigned'
                ]);

                // 🔹 Copier les items collectés
                $items = $order->collect->items->map(fn($item) => [
                    'delivery_id' => $delivery->id,
                    'product_id' => $item->product_id,
                    'quantity_collected' => $item->quantity_collected,
                    'quantity_delivered' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                if ($items->isNotEmpty()) {
                    DeliveryItem::insert($items->toArray());
                }

                // 🔹 Update commande
                $order->update([
                    'status' => OrderStatus::DELIVERY_ASSIGNED,
                    'delivery_status' => 'assigned'
                ]);

                return $delivery;
            });

            return Helpers::success($delivery, 'Livraison assignée avec succès ✅');

        } catch (\Exception $e) {
            Log::error('assignDelivery error: ' . $e->getMessage());
            return Helpers::validation('Erreur serveur, réessayez plus tard', 500);
        }
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
