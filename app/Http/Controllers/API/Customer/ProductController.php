<?php


namespace App\Http\Controllers\API\Customer;


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
        return Helpers::success(ProductResource::collection(Product::all()));
    }

    public function show(Product $product)
    {

        return Helpers::success(new ProductResource($product));
    }

}
