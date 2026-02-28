<?php


namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Resources\CustomerResource;
use App\Models\Address;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        // 🔹 Récupérer le terme de recherche
        $search = $request->query('search');

        // 🔹 Requête de base avec relations et tri par dernier créé
        $query = Customer::with('user','addresses')->latest();

        // 🔹 Filtrer par nom ou email si un terme de recherche est présent
        if ($search) {
            $query->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 🔹 Pagination
        $customers = $query->paginate(10);

        // 🔹 Retourner avec la Resource
        return Helpers::success(CustomerResource::collection($customers));
    }

    public function show(Customer $customer)
    {
        $customer=$customer->load('user','addresses','orders.items.product');
        return Helpers::success(new CustomerResource($customer));
    }
    public function update(Customer $customer, Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $customer->user_id,
            'phone' => 'sometimes|string|max:20',
        ]);

        $user=$customer->user;
        $user->update($validated);
        $customer->update([
            'full_name'=>$validated['name']
        ]);

        return Helpers::success(new CustomerResource($customer));
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
            'map_label'   => 'nullable|string',
            'description' => 'required|string'
        ]);

        $customer = auth()->user()->customer;

        $data['customer_id'] = $customer->id;
        $data['zone_id'] = 1;

        $address = Address::create($data);

        return Helpers::success($address);
    }

}
