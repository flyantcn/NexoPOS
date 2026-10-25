<?php

namespace Modules\NsMultiStore\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Modules\NsMultiStore\Services\StoreService;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentStore
{
    /**
     * Determine the store used by the current request and share it
     * with the rest of the application.
     */
    public function handle( Request $request, Closure $next ): Response
    {
        $store = null;

        if ( $request->hasSession() ) {
            $user = $request->user();

            /**
             * @var StoreService $storeService
             */
            $storeService = app()->make( StoreService::class );

            $store = $storeService->resolveCurrentStore(
                $user instanceof User ? $user : null,
                $request->session()->get( StoreService::SESSION_KEY )
            );

            $request->session()->put( StoreService::SESSION_KEY, $store?->id );
        }

        app()->make( StoreService::class )->defineCurrentStore( $store );

        return $next( $request );
    }
}
