<?php


namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Resources\OrderMiniResource;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        return Helpers::success(Product::all());
    }

    public function show(Product $product)
    {

        return Helpers::success(new ProductResource($product));
    }
    public function store(Request $request)
    {
        $request->validate([
            'name'=>'required|string',
            'volume_liters'=>'required|integer',
            'base_price'=>'required|numeric'
        ]);

        $product = Product::create($request->all());
        return response()->json($product);
    }
    public function update(Request $request,Product $product)
    {
        $request->validate([
            'name'=>'required|string',
            'volume_liters'=>'required|integer',
            'base_price'=>'required|numeric'
        ]);

        $product = $product->update($request->all());
        return response()->json($product);
    }
}
