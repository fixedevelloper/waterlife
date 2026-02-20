<?php


namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Models\Address;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function index()
    {
        return Customer::with('user','addresses')->get();
    }

    public function show(Customer $customer)
    {
        return $customer->load('user','addresses','orders.items.product');
    }
    public function addresses()
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return Helpers::error("Aucun client associé à cet utilisateur.");
        }

        return Helpers::success($customer->addresses);
    }

    public function storeAddresse(Request $request)
    {
        $data = $request->validate([
            'longitude'   => 'required|numeric',
            'latitude'    => 'required|numeric',
            'label'       => 'required|string',
            'description' => 'required|string'
        ]);

        $customer = auth()->user()->customer;

        $data['customer_id'] = $customer->id;
        $data['zone_id'] = 1;

        $address = Address::create($data);

        logger($address);
        return Helpers::success($address);
    }

}
