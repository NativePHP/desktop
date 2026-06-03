<?php

namespace Native\Desktop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Http\Middleware\Sample;

class OptionalNightwatchNever
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (class_exists(Sample::class)) {
            $middleware = app(Sample::class);

            return $middleware->handle($request, $next, 0.0);
        }

        return $next($request);
    }
}
