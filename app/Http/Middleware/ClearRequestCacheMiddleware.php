<?php

namespace App\Http\Middleware;

use App\Events\ResponseReadyEvent;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClearRequestCacheMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse) $next
     * @return Response|RedirectResponse
     */
    public function handle( Request $request, Closure $next )
    {
        $response = null;

        try {
            $response = $next( $request );
        } finally {
            /**
             * Even when the request fails (for example with a permission
             * error), the request scoped cache must be cleared. Otherwise a
             * stale permission cache keeps rejecting the user until its TTL
             * (1 hour) expires.
             */
            ResponseReadyEvent::dispatch( $response );
        }

        return $response;
    }
}
