<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Resources\AgentMiniResource;
use App\Http\Resources\AgentResource;
use Illuminate\Http\Request;
use App\Models\Agent;

class AgentController extends Controller
{
    public function index()
    {
        return Helpers::success(
            AgentMiniResource::collection(
                Agent::with('user', 'zone')->get()
            )
        );
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email',
            'phone' => 'sometimes|string|max:20',
            'can_collect' => 'sometimes',
            'can_deliver' => 'sometimes',
        ]);

        $agent = Agent::create($validated);
        return response()->json($agent);
    }
    public function update(Agent $agent, Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $agent->user_id,
            'phone' => 'sometimes|string|max:20',
            'can_collect' => 'sometimes',
            'can_deliver' => 'sometimes',
        ]);

        $user=$agent->user;
        $user->update($validated);
        $agent->update([
            'can_collect'=>$validated['can_collect'],
            'can_deliver'=>$validated['can_deliver']
        ]);

        return Helpers::success(new AgentResource($agent));
    }
    public function show(Agent $agent)
    {
        return Helpers::success(new AgentResource( $agent->load('user','zone')));
    }
}
