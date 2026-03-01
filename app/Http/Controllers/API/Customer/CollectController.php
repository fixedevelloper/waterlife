<?php


namespace App\Http\Controllers\API\Customer;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Helpers\OrderStatus;
use App\Http\Helpers\ResponseHelper;
use App\Http\Resources\CollectResource;
use Illuminate\Http\Request;
use App\Models\Collect;
use App\Models\CollectItem;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CollectController extends Controller
{
    // Lister collectes
    public function index(Request $request)
    {
        // Récupérer l'agent connecté
        $agent = Auth::user(); // ou Auth::user()->id selon ton modèle

        // Pagination
        $perPage = $request->input('per_page', 10); // nombre par page, 10 par défaut
        $page = $request->input('page', 1);

        // Récupérer les livraisons assignées à l'agent
        $deliveries = Collect::with(['order.items.product', 'items'])
            ->where('collector_id', $agent->agent->id)
            ->orderByDesc('collected_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return ResponseHelper::success(
            CollectResource::collection($deliveries),
            'Liste des livraisons paginée'
        );
    }
    public function lastCollects()
    {
        $collects = Collect::with(['order.items.product','items'])
            ->orderByDesc('collected_at')
            ->limit(5)
            ->get();

        return Helpers::success($collects);
    }
    // Assigner collecteur
    public function assign(Request $request)
    {
        $request->validate([
            'order_id'=>'required|exists:orders,id',
            'collector_id'=>'required|exists:agents,id'
        ]);

        $collect = Collect::create([
            'order_id'=>$request->order_id,
            'collector_id'=>$request->collector_id,
            'status'=>'assigned'
        ]);

        return response()->json($collect);
    }

    public function assignCollector(Request $request)
    {
        $user = Auth::user();

        // 🔐 Vérifier que l'utilisateur est connecté et est un agent
        if (!$user || !$user->agent) {
            return Helpers::validation('Non autorisé', 403);
        }

        // ✅ Validation stricte
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        try {
            $collect = DB::transaction(function () use ($request, $user) {
                $order = Order::with(['items', 'collect'])->findOrFail($request->order_id);

                // 🔴 Vérifier statut commande
                if (in_array($order->status, ['delivered', 'cancelled'])) {
                    return Helpers::validation('Impossible de s’assigner cette commande');
                }

                $existingCollect = $order->collect;

                // ✅ Si une collecte existe déjà pour cette commande
                if ($existingCollect) {
                    // Même agent → update status
                    if ($existingCollect->collector_id == $user->agent->id) {
                        $existingCollect->update(['status' => 'assigned']);
                        return $existingCollect;
                    }

                    // Autre agent → annuler la collecte existante
                    $existingCollect->update(['status' => 'cancelled']);
                    CollectItem::where('collect_id', $existingCollect->id)->delete();
                }

                // 🔹 Créer une nouvelle collecte pour cette commande
                $collect = Collect::create([
                    'order_id' => $order->id,
                    'collector_id' => $user->agent->id,
                    'status' => 'assigned'
                ]);

                // Copier les items
                $items = $order->items->map(fn($item) => [
                    'collect_id' => $collect->id,
                    'product_id' => $item->product_id,
                    'quantity_ordered' => $item->quantity,
                    'quantity_collected' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                Log::info('Items à insérer dans collect_items : ', $items->toArray());
                CollectItem::insert($items->toArray());

                // 🔹 Mettre à jour la commande
                $order->update([
                    'status' => OrderStatus::COLECTOR_ASSIGNED,
                    'collector_id' => $user->agent->id,
                    'collection_status' => 'assigned'
                ]);

                return $collect;
            });

            // ✅ Retour unifié avec Helpers
            return Helpers::success($collect, 'Commande assignée avec succès ✅');

        } catch (\Exception $e) {
            Log::error('assignCollector error: ' . $e->getMessage());
            return Helpers::validation('Erreur serveur, réessayez plus tard', 500);
        }
    }

    // Marquer collecte terminée avec items collectés
    public function complete(Request $request, Collect $collect)
    {
        // 🔐 Vérifier que l'agent connecté est bien celui de la collecte
        $user = Auth::user();
        if (!$user || !$user->agent || $collect->collector_id !== $user->agent->id) {
            return Helpers::validation('Non autorisé à compléter cette collecte', 403);
        }

        // Validation des items
        $request->validate([
            'items' => 'required|array|min:1', // array de {product_id, quantity_collected}
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity_collected' => 'required|numeric|min:0'
        ]);

        try {
            DB::transaction(function () use ($request, $collect) {
                foreach ($request->items as $item) {
                    CollectItem::updateOrCreate(
                        ['collect_id' => $collect->id, 'product_id' => $item['product_id']],
                        ['quantity_collected' => $item['quantity_collected']]
                    );
                }

                // Update statut de la collecte
                $collect->update([
                    'status' => 'collected',
                    'collected_at' => now()
                ]);

                // Mettre à jour la commande associée
                $order = $collect->order;
                $order->update([
                    'collection_status' => 'collected',
                    'status' => 'processing' // 🔹 peut être mis à "ready_for_delivery" si tu veux
                ]);
            });

            return Helpers::success(
                $collect->load('items'),
                'Collecte complétée avec succès ✅'
            );

        } catch (\Exception $e) {
            Log::error('completeCollect error: ' . $e->getMessage());
            return Helpers::validation('Erreur serveur, réessayez plus tard', 500);
        }
    }
    // Voir détails d'une commande
    public function show($id)
    {
        $collect = Collect::with([
            'order.items.product',
            'items.product',
            'collector'
        ])->findOrFail($id);

        return new CollectResource($collect);
    }
    public function collect_show($orderId)
    {
        $collect = Collect::with('items.product')
            ->where('order_id', $orderId)
            ->firstOrFail();

        $items = $collect->items->map(function ($item) {
            $product = $item->product;

            return [
                'id' => $item->id,
                'name' => $product?->name ?? 'Produit inconnu',
            'volume' => $product?->volume_liters ? $product->volume_liters . 'L' : '',
            'quantity_ordered' => $item->quantity_ordered ?? 0,
            'quantity_collected' => $item->quantity_collected ?? 0,
        ];
    });

        return Helpers::success([
            'collect_id' => $collect->id,
            'status' => $collect->status,
            'collected_at' => $collect->collected_at,
            'items' => $items
        ]);
    }
    public function update(Request $request, $collectId)
    {
        // Décoder items si c'est une string JSON
        if (is_string($request->items)) {
            $request->merge([
                'items' => json_decode($request->items, true)
            ]);
        }

        // Logger pour vérifier
        logger($request->all());

        $data = $request->validate([
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:collect_items,id',
            'items.*.quantity_collected' => 'required|integer|min:0',
            'collection_image' => 'nullable|image|max:2048', // 2MB
        ]);

        logger($data);

        // Mettre à jour les items
        foreach ($data['items'] as $item) {
            CollectItem::where('id', $item['item_id'])
                ->update([
                    'quantity_collected' => $item['quantity_collected']
                ]);
        }

        // Sauvegarder la photo si fournie
        $imagePath = null;
        if ($request->hasFile('collection_image')) {
            $file = $request->file('collection_image');
            $imagePath = $file->store('collects', 'public');
        }

        // Mettre à jour la collecte
        Collect::where('id', $collectId)->update([
            'status' => 'collected',
            'collected_at' => now(),
            'collection_image' => $imagePath ?? null
        ]);

        return Helpers::success([
            'message' => 'Collecte validée'
        ]);
    }
}
