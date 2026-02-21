<?php


namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Resources\CollectResource;
use Illuminate\Http\Request;
use App\Models\Collect;
use App\Models\CollectItem;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        return Helpers::success([
            'data' => CollectResource::collection($deliveries),
            'current_page' => $deliveries->currentPage(),
            'last_page' => $deliveries->lastPage(),
            'total' => $deliveries->total(),
            'per_page' => $deliveries->perPage()
        ]);
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
        $request->validate([
            'order_id'=>'required|exists:orders,id',
            'collector_id'=>'required|exists:agents,id'
        ]);
        $orderId=$request->order_id;
        DB::transaction(function () use ($orderId, $request) {

            $order = Order::with('items')->findOrFail($orderId);

            // 🔹 Eviter double collecte
            if ($order->collect) {
                throw ValidationException::withMessages([
                    'collect' => 'Collecte déjà existante'
                ]);
            }

            // 🔹 Créer la collecte
            $collect = Collect::create([
                'order_id' => $order->id,
                'collector_id' => $request->collector_id,
                'status' => 'pending'
            ]);

            // 🔹 Copier les items
            $items = $order->items->map(function ($item) use ($collect) {
                return [
                    'collect_id' => $collect->id,
                    'product_id' => $item->product_id,
                    'quantity_ordered' => $item->quantity,
                    'quantity_collected' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            });

            CollectItem::insert($items->toArray());

            // 🔹 Update commande
            $order->update([
                'status' => 'assigned'
            ]);
        });

        return response()->json([
            'message' => 'Collecte créée avec succès'
        ]);
    }

    // Marquer collecte terminée avec items collectés
    public function complete(Request $request, Collect $collect)
    {
        $request->validate([
            'items'=>'required|array|min:1' // array de {product_id, quantity_collected}
        ]);

        foreach($request->items as $item){
            CollectItem::updateOrCreate(
                ['collect_id'=>$collect->id,'product_id'=>$item['product_id']],
                ['quantity_collected'=>$item['quantity_collected']]
            );
        }

        $collect->update(['status'=>'collected','collected_at'=>now()]);

        // Mettre à jour la commande
        $order = $collect->order;
        $order->update(['collection_status'=>'collected','status'=>'processing']);

        return response()->json($collect->load('items'));
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
        $collect = Collect::with([
            'items.product'
        ])
            ->where('order_id', $orderId)
            ->firstOrFail();

        $items = $collect->items->map(function ($item) {

            return [
                'id' => $item->id,
                'name' => $item->product?->name ?? 'Produit inconnu',
        'volume' => $item->product?->volume_liters
                ? $item->product->volume_liters . 'L'
                : '',
        'quantity_ordered' => $item->quantity_ordered ?? 0,
        'quantity_collected' => $item->quantity_collected ?? 0
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
