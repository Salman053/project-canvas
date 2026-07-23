<?php

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        logger('Request: '.$request->method().' '.$request->path());

        return $next($request);
    }
}
