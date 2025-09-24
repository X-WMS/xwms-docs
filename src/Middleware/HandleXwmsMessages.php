<?php

namespace XWMS\Package\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleXwmsMessages
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('xwmsmessage_status') && $request->has('xwmsmessage_message')) {
            // Flash the message into the session
            session()->flash(
                $request->get('xwmsmessage_status'),
                urldecode($request->get('xwmsmessage_message'))
            );

            // Rebuild URL without xwmsmessage parameters
            $cleanUrl = url()->current();
            $otherQueries = collect($request->query())->except(['xwmsmessage_status', 'xwmsmessage_message']);

            if ($otherQueries->isNotEmpty()) {
                $cleanUrl .= '?' . http_build_query($otherQueries->toArray());
            }

            return redirect($cleanUrl);
        }

        return $next($request);
    }
}
