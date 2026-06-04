<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupIsAvailable
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (User::query()->exists()) {
            abort(404);
        }

        if ($request->isMethod('POST')) {
            DemoMode::ensureAllowed('registration');
        }

        return $next($request);
    }
}
