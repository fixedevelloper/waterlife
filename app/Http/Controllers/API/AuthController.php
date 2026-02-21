<?php


namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Customer;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // -----------------------------
    // Inscription (client ou agent)
    // -----------------------------
    public function register(Request $request)
    {
        DB::beginTransaction();
        $request->validate([
            'phone'=>'required|string|unique:users,phone',
            'password'=>'required|string|min:6',
            'fullname'=>'required|string',
            'email'=>'required|string',
            'role'=>'required|in:customer,agent',
        ]);

        $user = User::create([
            'email'=>$request->email,
            'name'=>$request->fullname,
            'phone'=>$request->phone,
            'password'=>Hash::make($request->password),
            'role'=>$request->role,
        ]);

        if($request->role === 'customer'){
            Customer::create(['user_id'=>$user->id,'full_name'=>$request->full_name ?? '']);
        }else{
            Agent::create([
                'user_id'=>$user->id,
                'zone_id'=>$request->zone_id ?? null,
                'can_collect'=>true,
                'can_deliver'=>true,
                'is_available'=>true
            ]);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        DB::commit();
        return response()->json([
            'data'=>[
                'user'=>$user,
                'token'=>$token
            ]
        ]);
    }

    // -----------------------------
    // Login
    // -----------------------------
    public function login(Request $request)
    {
        $request->validate([
            'phone'=>'required|string',
            'password'=>'required|string',
        ]);

        $user = User::where('phone',$request->phone)->first();

        if(!$user || !Hash::check($request->password,$user->password)){
            throw ValidationException::withMessages([
                'phone'=>['Numéro ou mot de passe incorrect.']
            ]);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        $user->update(['last_login_at'=>now()]);

        return response()->json([
           'data'=>[
               'user'=>$user,
               'token'=>$token
           ]
        ]);
    }

    // -----------------------------
    // Logout
    // -----------------------------
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message'=>'Déconnexion réussie']);
    }

    // -----------------------------
    // Infos du profil connecté
    // -----------------------------
    public function me(Request $request)
    {
        $user = $request->user();
        if($user->role === 'client'){
            $user->load('customer.addresses');
        }else{
            $user->load('agent.zone');
        }

        return response()->json($user);
    }
}
