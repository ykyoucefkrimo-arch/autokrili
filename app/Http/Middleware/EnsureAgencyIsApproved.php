<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the agency dashboard. A pending agency is not sent to an empty
 * dashboard but to a dedicated screen (specification 3.2); a suspended one
 * keeps read access, so only write verbs are refused.
 */
class EnsureAgencyIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $agency = $request->user()?->activeAgency();

        if (! $agency) {
            abort(404);
        }

        if ($agency->status !== Agency::STATUS_APPROVED) {
            if ($agency->isSuspended() && $request->isMethodSafe()) {
                return $next($request);
            }

            return redirect()->route('agency.pending');
        }

        return $next($request);
    }
}
