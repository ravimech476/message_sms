<?php

namespace App\Http\Middleware;

use App\Models\Practice;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AttachPractice
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->has('domain')) {
            try {
                $domain = $request->get('domain');
                $practice = Practice::whereHas('domain', function(Builder $query) use($domain) {
                                    $query->where('domain', $domain);
                                })->firstOrFail();
                $request->attributes->add(['practice' => $practice]);
            } catch (ModelNotFoundException $e) {
                $error = "Domain '{$request->get('domain')}' not found in our database. Please contact us.";

                return response()->json(['message' => $error], 404);
            }
        }

        return $next($request);
    }
}
