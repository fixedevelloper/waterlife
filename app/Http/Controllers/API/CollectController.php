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
}
