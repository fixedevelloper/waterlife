<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Customer\CollectController;
use App\Http\Controllers\API\Customer\DeliveryController;
use App\Http\Controllers\API\Customer\OrderController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class,'register']);
Route::post('login', [AuthController::class,'login'])->name('login');

Route::middleware('auth:sanctum')->group(function(){
    Route::post('logout', [AuthController::class,'logout']);
    Route::get('me', [AuthController::class,'me']);
    Route::get('addresses', [CustomerController::class, 'addresses']);
    Route::post('addresses', [CustomerController::class, 'storeAddresse']);

    Route::get('collects', [CollectController::class, 'index']);
    Route::post('collects/assign', [CollectController::class, 'assign']); // assigner collecteur
    Route::post('collects/{collect}/complete', [CollectController::class, 'complete']); // marquer collecté
    Route::get('collects/lasts', [CollectController::class, 'lastCollects']);
    Route::get('collects/{id}/detail', [CollectController::class, 'show']);
    Route::get('collects/{id}/comparaison', [CollectController::class, 'collect_show']);
    Route::post('collects/{id}/update', [CollectController::class, 'update']);
    // Livraisons
// -----------------------------
    Route::get('deliveries', [DeliveryController::class, 'index']);
    Route::post('deliveries/assign', [DeliveryController::class, 'assign']); // assigner livreur
    Route::post('deliveries/{delivery}/complete', [DeliveryController::class, 'complete']); // marquer livré
    Route::get('deliveries/lasts', [DeliveryController::class, 'lastDeliveries']);
    Route::get('deliveries/{id}/detail', [DeliveryController::class, 'show']);
// -----------------------------
// Produits disponibles (bidons)
// -----------------------------
Route::get('products', [ProductController::class, 'index']);

// -----------------------------
// Clients
// -----------------------------
Route::get('customers', [CustomerController::class, 'index']);
Route::get('customers/{customer}', [CustomerController::class, 'show']);

// -----------------------------
// Commandes
// -----------------------------
    Route::get('orders-processings', [OrderController::class, 'processingOrders']);
Route::get('orders', [OrderController::class, 'index']); // toutes les commandes du client connecté
Route::get('orders-recents', [OrderController::class, 'recentOrders']); // recents les commandes du client connecté
Route::get('orders/{order}', [OrderController::class, 'show']); // détails
Route::post('orders', [OrderController::class, 'store']); // créer commande
Route::post('orders/preview', [OrderController::class, 'preview']); // créer commande
    Route::get('orders/{orderNumber}/show', [OrderController::class, 'showByOrderNumber']);
    Route::put('orders/{orderNumber}/{status}', [OrderController::class, 'updateByNumber']);
// -----------------------------
// Paiements
// -----------------------------
Route::get('payments', [PaymentController::class, 'index']); // paiements du client
Route::post('payments', [PaymentController::class, 'store']); // enregistrer paiement
});
