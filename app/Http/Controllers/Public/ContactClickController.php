<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Vehicle;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;

/**
 * Records a click on an agency's phone or WhatsApp button.
 *
 * It is the strongest signal the public side produces — a visitor who calls is
 * worth more than a hundred who scroll — and the browser is the only place
 * that knows it happened.
 */
class ContactClickController extends Controller
{
    public function __construct(private readonly StatsService $stats)
    {
    }

    public function __invoke(Vehicle $vehicle): JsonResponse
    {
        if ($vehicle->isPublished() && $vehicle->agency?->status === Agency::STATUS_APPROVED) {
            $this->stats->recordContactClick($vehicle);
        }

        // 204 : le navigateur n'attend rien, et le lien tel: part en parallele.
        return response()->json(null, 204);
    }
}
