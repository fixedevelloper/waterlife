<?php


namespace App\Http\Controllers;


use App\Models\Order;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function show(Order $order, Request $request)
    {
        $payment = $order->payment;

        logger($payment);
        if (!$payment || $payment->token !== $request->token) {
            abort(403);
        }

        return view('payment.pay', compact('order'));
    }

    public function success(Order $order)
    {
        $order->payment->update([
            'status' => 'success'
        ]);

        return view('payment.success');
    }

    public function cancel(Order $order)
    {
        $order->payment->update([
            'status' => 'failed'
        ]);
        return view('payment.cancel');
    }
}
