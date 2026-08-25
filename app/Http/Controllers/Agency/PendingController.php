<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The waiting screen of specification 3.2. An agency that is not yet approved
 * lands here rather than on an empty dashboard, which would look broken.
 */
class PendingController extends Controller
{
    public function __invoke(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $agency = $request->user()->activeAgency();

        abort_unless($agency, 404);

        if ($agency->status === Agency::STATUS_APPROVED) {
            return redirect()->route('agency.dashboard');
        }

        return Inertia::render('Agency/Pending', [
            'agency' => [
                'commercial_name' => $agency->commercial_name,
                'status' => $agency->status,
                'rejection_reason' => $agency->rejection_reason,
                'submitted_at' => $agency->created_at->translatedFormat('d F Y'),
            ],
        ]);
    }
}
