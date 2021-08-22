<?php

namespace Taksu\Restful\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Check based on HTTP method, whether user can do a certain action
 */
class CrudAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $resource = $this->resolveResourceName($request);

        $method = $request->getMethod();

//        dd($method, !Auth::user()->can("$resource.read"), "$resource.read");

        switch ($method) {
            case "POST":
                if (!Auth::user()->can("$resource.create")) {
                    abort(403);
                }
                break;

            case "GET":
                if (!Auth::user()->can("$resource.read")) {
                    abort(403);
                }
                break;

            case "PUT":
            case "PATCH":
                if (!Auth::user()->can("$resource.update")) {
                    abort(403);
                }
                break;

            case "DELETE":
                if (!Auth::user()->can("$resource.delete")) {
                    abort(403);
                }
                break;
        }

        return $next($request);
    }

    protected function resolveResourceName(Request $request)
    {
        $path = $request->path();

        // example of the path: /api/customer/:id, or /api/customers/:id
        // therefore, we get the second string
        $arr = explode("/", $path);
        $name = "";
        if (sizeof($arr) < 1) {
            throw new Exception("resolveResourceName is not correct.");
        } elseif (sizeof($arr) > 1 && $arr[1]) {
            $name = $arr[1];
        }

        return $name;
    }
}
