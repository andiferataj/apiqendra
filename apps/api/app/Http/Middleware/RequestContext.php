<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestContext
{
    public function handle(Request $request, Closure $next): mixed
    {
        $id = (string) Str::uuid();
        $request->attributes->set('request_id', $id);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
