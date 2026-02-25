<?php


namespace App\Http\Controllers\API\Customer;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        return Helpers::success(Product::all());
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
}
