<?php


namespace App\Http\Controllers\API;

use App\Http\Helpers\Helpers;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerResource;
use Illuminate\Support\Facades\Hash;

class ManagerController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $agents = Manager::with(['user', 'zone'])
            ->latest()
            ->paginate($perPage);

        return Helpers::success(
            ManagerResource::collection($agents)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email',
            'phone' => 'sometimes|string|max:20',
            'forage_name' => 'nullable|string',
        ]);

        $user=User::create([
            'name'=>$validated['name'],
            'email'=>$validated['email'],
            'phone'=>$validated['phone'],
            'password'=>Hash::make('password'),
            'role'=>'manger'
        ]);
        $manager = Manager::create([
            'user_id'=>$user->id
        ]);

        return new ManagerResource($manager->load(['user', 'zone']));
    }

    public function show(Manager $manager)
    {
        return new ManagerResource($manager->load(['user', 'zone']));
    }

    public function update(Request $request, Manager $manager)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $manager->user_id,
            'phone' => 'sometimes|string|max:20',
            'forage_name' => 'nullable|string',
            'is_active' => 'boolean',
        ]);


        $user=$manager->user;
        $user->update([
            'name'=>$validated['name'],
            'email'=>$validated['email'],
            'phone'=>$validated['phone'],
        ]);
        $manager->update([
           'is_active'=>$validated['is_active']
        ]);

        return new ManagerResource($manager);
    }

    public function destroy(Manager $manager)
    {
        $manager->delete();

        return response()->json(['message' => 'Manager supprimé']);
    }
}
