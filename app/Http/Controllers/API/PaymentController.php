<?php


namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        return Payment::with('order')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'order_id'=>'required|exists:orders,id',
            'method'=>'required|in:cash,mobile_money',
            'amount'=>'required|numeric'
        ]);

        $payment = Payment::create($request->all());
        return response()->json($payment);
    }
}

