<?php

namespace App\Http\Middleware;

use App\Support\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDemoMode
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  'uploads'|'imports'|'account_changes'|'deletes'|'registration'|'password_reset'|'email_sending'|'external_webhooks'  $capability
     */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        if ($capability === 'uploads') {
            DemoMode::processUploadRequest($request);
        } elseif ($capability === 'account_changes') {
            DemoMode::ensureAccountChangesAllowed($request->user());
        } else {
            DemoMode::ensureAllowed($capability);
        }

        return $next($request);
    }
}
