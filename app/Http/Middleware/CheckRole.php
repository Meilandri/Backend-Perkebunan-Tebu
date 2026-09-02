<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user() || $request->user()->peran_user !== $role) {
            return response()->json([
                'message' => 'Unauthorized. Akses ditolak untuk peran ini.'
            ], 403);
        }

        return $next($request);
    }
}
