<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

class AdminLocale
{
    public function handle(Request $request, Closure $next)
    {
        $previous = app()->getLocale();
        $carbonLocale = Carbon::getLocale();
        app()->setLocale('ru');
        Carbon::setLocale('ru');

        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
            Carbon::setLocale($carbonLocale);
        }
    }
}
