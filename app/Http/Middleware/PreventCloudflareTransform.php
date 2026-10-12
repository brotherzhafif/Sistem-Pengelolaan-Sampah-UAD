<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventCloudflareTransform
{
    /**
     * Handle an incoming request.
     *
     * Injects 'no-transform' into Cache-Control to instruct Cloudflare edge
     * to avoid modifying HTML or delaying JavaScript execution with Rocket Loader.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $cacheControl = $response->headers->get('Cache-Control', '');
        if (! str_contains($cacheControl, 'no-transform')) {
            $newHeader = trim(($cacheControl ? $cacheControl . ', ' : '') . 'no-transform');
            $response->headers->set('Cache-Control', $newHeader);
        }

        return $response;
    }
}
