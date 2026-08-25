<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agency statistics of specification 8, gated by the plan's stats_level.
 *
 * The locked blocks are computed and sent anyway: the page blurs them and
 * offers the upgrade. Sending empty figures behind the blur would advertise a
 * feature with a lie.
 */
class StatsController extends Controller
{
    public function __construct(private readonly StatsService $stats)
    {
    }

    public function __invoke(Request $request): Response
    {
        $agency = $request->user()->activeAgency();
        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        return Inertia::render('Agency/Stats', [
            'stats' => $this->stats->forAgency($agency, $days),
            'plan' => [
                'name' => $agency->currentPlan()?->name,
                'stats_level' => $agency->currentPlan()?->stats_level ?? 'basic',
            ],
            'days' => $days,
        ]);
    }
}
