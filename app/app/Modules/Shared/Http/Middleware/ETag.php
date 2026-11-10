<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ETag
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('http_cache.etag_enabled')
            || ! $request->isMethodCacheable()
            || $response->getStatusCode() !== Response::HTTP_OK) {
            return $response;
        }

        $response->setEtag(sha1((string) $response->getContent()));
        $response->headers->set('Cache-Control', 'no-cache');

        $response->isNotModified($request);

        return $response;
    }
}
