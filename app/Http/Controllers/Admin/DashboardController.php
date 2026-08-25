<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Plan;
use App\Models\Vehicle;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'pending' => Agency::where('status', Agency::STATUS_PENDING)->count(),
                'approved' => Agency::where('status', Agency::STATUS_APPROVED)->count(),
                'rejected' => Agency::where('status', Agency::STATUS_REJECTED)->count(),
                'suspended' => Agency::where('status', Agency::STATUS_SUSPENDED)->count(),
                // La file des annonces se traite depuis le meme ecran que celle
                // des agences : l'administrateur ouvre une page, pas deux.
                'vehicles_pending' => Vehicle::where('status', Vehicle::STATUS_PENDING)->count(),
                'vehicles_published' => Vehicle::where('status', Vehicle::STATUS_PUBLISHED)->count(),
            ],
            // Plan distribution reads through the active subscription so an
            // agency without one is simply absent rather than counted wrong.
            'byPlan' => Plan::query()
                ->orderBy('sort_order')
                ->withCount(['subscriptions as agencies_count' => fn ($q) => $q->where('status', 'active')])
                ->get(['id', 'name', 'slug', 'badge_color']),
            'latest' => Agency::query()
                ->with('wilaya:id,name_fr')
                ->latest()
                ->take(5)
                ->get(['id', 'commercial_name', 'status', 'wilaya_id', 'created_at'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'commercial_name' => $a->commercial_name,
                    'status' => $a->status,
                    'wilaya' => $a->wilaya?->name_fr,
                    'created_at' => $a->created_at->translatedFormat('d M Y'),
                ]),
        ]);
    }
}
