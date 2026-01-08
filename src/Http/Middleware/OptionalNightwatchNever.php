<?php

namespace Native\Desktop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OptionalNightwatchNever
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (class_exists(\Laravel\Nightwatch\Http\Middleware\Sample::class)) {
            $middleware = app(\Laravel\Nightwatch\Http\Middleware\Sample::class);

            return $middleware->handle($request, $next, 0.0);
        }

        return $next($request);
    }
}
