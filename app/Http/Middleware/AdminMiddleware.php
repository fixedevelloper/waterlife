<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Vérifie si l'utilisateur est connecté
        if (!$user) {
            return response()->json([
                'message' => 'Non authentifié'
            ], 401);
        }

        // Vérifie si c'est un admin
        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Accès interdit (admin uniquement)'
            ], 403);
        }

        // Vérifie si actif
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Compte désactivé'
            ], 403);
        }

        return $next($request);
    }
}
