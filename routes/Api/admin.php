<?php

use App\Http\Controllers\API\AgentController;
use App\Http\Controllers\API\CollectController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\DeliveryController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
// -----------------------------
// Produits
// -----------------------------
Route::get('products', [ProductController::class, 'index']);
Route::post('products', [ProductController::class, 'store']);

// -----------------------------
// Clients
// -----------------------------
Route::get('customers', [CustomerController::class, 'index']);
Route::get('customers/{customer}', [CustomerController::class, 'show']);

// -----------------------------
// Commandes
// -----------------------------
Route::get('orders', [OrderController::class, 'index'])->middleware();
Route::get('orders/{order}', [OrderController::class, 'show']);
Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']); // changer status

// -----------------------------
// Collectes
// -----------------------------
Route::get('collects', [CollectController::class, 'index']);
Route::post('collects/assign', [CollectController::class, 'assign']); // assigner collecteur
Route::post('collects/{collect}/complete', [CollectController::class, 'complete']); // marquer collecté
Route::get('collects/lasts', [CollectController::class, 'lastCollects']);
Route::get('collects/{id}/detail', [CollectController::class, 'show']);
    Route::get('collects/{id}/comparaison', [CollectController::class, 'collect_show']);
    Route::post('collects/{id}/update', [CollectController::class, 'update']);
// -----------------------------
// Livraisons
// -----------------------------
Route::get('deliveries', [DeliveryController::class, 'index']);
Route::post('deliveries/assign', [DeliveryController::class, 'assign']); // assigner livreur
Route::post('deliveries/{delivery}/complete', [DeliveryController::class, 'complete']); // marquer livré
Route::get('deliveries/lasts', [DeliveryController::class, 'lastDeliveries']);
    Route::get('deliveries/{id}/detail', [DeliveryController::class, 'show']);

// -----------------------------
// Agents
// -----------------------------
Route::get('agents', [AgentController::class, 'index']);
Route::get('agents/{agent}', [AgentController::class, 'show']);

// -----------------------------
// Paiements
// -----------------------------
Route::get('payments', [PaymentController::class, 'index']);
Route::post('payments', [PaymentController::class, 'store']);
});
