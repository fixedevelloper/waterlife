<?php


namespace App\Http\Controllers\API;

use App\Models\Manager;
use App\Models\Versement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\VersementResource;

class VersementController extends Controller
{
    public function index()
    {
        return VersementResource::collection(
            Versement::with(['manager', 'validator'])->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'manager_id' => 'required|exists:managers,id',
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:cash,mobile_money,bank',
            'reference' => 'nullable|string',
            'provider' => 'nullable|string',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $versement = Versement::create($data);

        return new VersementResource($versement->load('manager'));
    }

    // 🔥 VALIDATION (LOGIQUE MÉTIER)
    public function validateVersement($id)
    {
        $versement = Versement::findOrFail($id);

        if ($versement->status !== 'pending') {
            return response()->json(['message' => 'Déjà traité'], 400);
        }

        $manager = $versement->manager;

        // 🔥 Déduction du solde
        $manager->balance -= $versement->amount;
        $manager->save();

        $versement->update([
            'status' => 'validated',
            'validated_by' => auth()->id(),
            'validated_at' => now()
        ]);

        return new VersementResource($versement->load(['manager', 'validator']));
    }

    public function reject($id)
    {
        $versement = Versement::findOrFail($id);

        $versement->update([
            'status' => 'rejected',
            'validated_by' => auth()->id(),
            'validated_at' => now()
        ]);

        return new VersementResource($versement);
    }
}
