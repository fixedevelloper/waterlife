<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agent;

class AgentController extends Controller
{
    public function index()
    {
        return Agent::with('user','zone')->get();
    }

    public function show(Agent $agent)
    {
        return $agent->load('user','zone');
    }
}
