<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Vehicle;
use App\Models\Wilaya;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The XML sitemap of specification 7.2.
 *
 * Only pages that exist and answer 200 are listed: a sitemap that points at
 * empty result pages teaches a crawler to distrust the whole file. Wilayas
 * without a single published listing are therefore left out.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        // Rebuilt every hour rather than on every crawl: the catalogue moves
        // slowly, and a crawler can ask for this file often.
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'freq' => 'daily'],
            ['loc' => route('search'), 'priority' => '0.9', 'freq' => 'daily'],
            ['loc' => route('agency.register'), 'priority' => '0.5', 'freq' => 'monthly'],
        ];

        foreach (['about', 'terms', 'privacy', 'faq', 'contact'] as $page) {
            $urls[] = ['loc' => route("pages.{$page}"), 'priority' => '0.3', 'freq' => 'yearly'];
        }

        Wilaya::query()
            ->whereHas('vehicles', fn ($q) => $q->where('status', Vehicle::STATUS_PUBLISHED))
            ->get(['slug'])
            ->each(function (Wilaya $wilaya) use (&$urls) {
                $urls[] = [
                    'loc' => route('search.wilaya', $wilaya->slug),
                    'priority' => '0.8',
                    'freq' => 'daily',
                ];
            });

        Vehicle::visible()
            ->with('pickupWilaya:id,slug')
            ->get(['id', 'slug', 'updated_at', 'pickup_wilaya_id'])
            ->each(function (Vehicle $vehicle) use (&$urls) {
                $urls[] = [
                    'loc' => route('vehicle.show', ['vehicle' => $vehicle->id, 'slug' => $vehicle->slug]),
                    'lastmod' => $vehicle->updated_at->toAtomString(),
                    'priority' => '0.7',
                    'freq' => 'weekly',
                ];
            });

        Agency::where('status', Agency::STATUS_APPROVED)
            ->whereHas('vehicles', fn ($q) => $q->where('status', Vehicle::STATUS_PUBLISHED))
            ->get(['slug', 'updated_at'])
            ->each(function (Agency $agency) use (&$urls) {
                $urls[] = [
                    'loc' => route('agency.public', $agency->slug),
                    'lastmod' => $agency->updated_at->toAtomString(),
                    'priority' => '0.6',
                    'freq' => 'weekly',
                ];
            });

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';
            if (isset($url['lastmod'])) {
                $lines[] = '    <lastmod>'.$url['lastmod'].'</lastmod>';
            }
            $lines[] = '    <changefreq>'.$url['freq'].'</changefreq>';
            $lines[] = '    <priority>'.$url['priority'].'</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }
}
