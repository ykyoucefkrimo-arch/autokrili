<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Commune;
use App\Models\Vehicle;
use App\Models\Wilaya;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the full administrative division of Algeria — 69 wilayas and their
 * 1541 communes — from `database/data/cities.json`.
 *
 * WilayaSeeder stays the authority for the 58 historical wilayas: it carries
 * their accented French spelling, their Arabic name and the coordinates of
 * their chef-lieu, none of which the JSON has. This seeder adds the eleven
 * wilayas of the 2025 reform and, above all, the communes.
 *
 * It never rebuilds the tables. Agencies and listings point at commune ids, so
 * an import that renumbered them would silently move every agency in the
 * country. Rows are matched, updated, and only then completed.
 */
class AlgeriaDivisionsSeeder extends Seeder
{
    private const SOURCE = 'database/data/cities.json';

    /**
     * The eleven wilayas the JSON adds, with the accented spelling, the Arabic
     * name and the approximate coordinates of the chef-lieu — the three things
     * the file does not carry.
     *
     * [code => [name_fr, name_ar, latitude, longitude]]
     */
    private const NEW_WILAYAS = [
        '59' => ['Aflou', 'أفلو', 34.1119, 2.1006],
        '60' => ['El Abiodh Sidi Cheikh', 'الأبيض سيدي الشيخ', 32.8833, 0.5500],
        '61' => ['El Aricha', 'العريشة', 34.2167, -1.2667],
        '62' => ['El Kantara', 'القنطرة', 35.2167, 5.7000],
        '63' => ['Barika', 'بريكة', 35.3894, 5.3644],
        '64' => ['Bou Saâda', 'بوسعادة', 35.2131, 4.1817],
        '65' => ['Bir El Ater', 'بئر العاتر', 34.7500, 8.0600],
        '66' => ['Ksar El Boukhari', 'قصر البخاري', 35.8833, 2.7500],
        '67' => ['Ksar Chellala', 'قصر الشلالة', 35.2117, 2.3167],
        '68' => ['Aïn Oussera', 'عين وسارة', 35.4500, 2.9000],
        '69' => ['Messaad', 'مسعد', 34.1667, 3.5000],
    ];

    public function run(): void
    {
        $data = $this->readSource();

        DB::transaction(function () use ($data) {
            $wilayas = $this->syncWilayas($data['wilayas'] ?? []);
            $keptIds = $this->syncCommunes($data['communes'] ?? [], $wilayas);

            $this->retireOrphans($keptIds);
            $this->realignParents();
        });

        // Les listes de reference viennent d'etre reecrites sous le cache.
        app(\App\Services\ReferenceDataService::class)->forget();

        $this->command?->info(sprintf(
            'Découpage administratif : %d wilayas, %d communes.',
            Wilaya::count(),
            Commune::count()
        ));
    }

    /** @return array{wilayas: array, communes: array} */
    private function readSource(): array
    {
        $path = base_path(self::SOURCE);

        if (! is_file($path)) {
            throw new RuntimeException(
                "Fichier introuvable : {$path}. Déposez-y le cities.json des 69 wilayas."
            );
        }

        // A file exported from Windows often carries a BOM; json_decode chokes
        // on it and reports a syntax error that says nothing useful.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($path));
        $data = json_decode($raw, true);

        if (! is_array($data) || ! isset($data['wilayas'], $data['communes'])) {
            throw new RuntimeException(
                'JSON illisible : les clés « wilayas » et « communes » sont attendues.'
            );
        }

        return $data;
    }

    /**
     * @param  array<int, array>  $rows
     * @return array<int, Wilaya>  indexed by the JSON wilaya_id
     */
    private function syncWilayas(array $rows): array
    {
        $wilayas = [];

        foreach ($rows as $row) {
            $code = str_pad((string) $row['wilaya_id'], 2, '0', STR_PAD_LEFT);
            $existing = Wilaya::where('code', $code)->first();

            // The historical 58 keep the spelling WilayaSeeder gave them: the
            // JSON writes "Bejaia" and "Setif", and a public URL that loses its
            // accents is a URL that changes under the visitor's feet.
            if ($existing) {
                $existing->fill([
                    'name_ar' => $this->arabic($row['wilaya_name_arabic'] ?? null) ?: $existing->name_ar,
                ])->save();

                $wilayas[$row['wilaya_id']] = $existing;

                continue;
            }

            [$nameFr, $nameAr, $lat, $lng] = self::NEW_WILAYAS[$code]
                ?? [$row['wilaya_name_latin'], $this->arabic($row['wilaya_name_arabic'] ?? null) ?: $row['wilaya_name_latin'], null, null];

            $wilayas[$row['wilaya_id']] = Wilaya::create([
                'code' => $code,
                'name_fr' => $nameFr,
                'name_ar' => $nameAr,
                'slug' => $this->uniqueWilayaSlug($nameFr),
                'latitude' => $lat,
                'longitude' => $lng,
            ]);
        }

        return $wilayas;
    }

    /**
     * @param  array<int, array>  $rows
     * @param  array<int, Wilaya>  $wilayas
     * @return array<int, int>  ids of the communes the import kept or created
     */
    private function syncCommunes(array $rows, array $wilayas): array
    {
        $kept = [];
        $usedSlugs = [];

        foreach ($rows as $row) {
            $wilaya = $wilayas[$row['wilaya_id']] ?? null;

            if (! $wilaya) {
                // A commune attached to a wilaya the file never declares would
                // land nowhere; skipping it beats inventing a parent.
                continue;
            }

            $name = trim((string) $row['commune_name_latin']);
            $slug = $this->uniqueCommuneSlug($name, $wilaya->id, $usedSlugs);

            $commune = Commune::updateOrCreate(
                ['wilaya_id' => $wilaya->id, 'slug' => $slug],
                [
                    'name_fr' => $name,
                    'name_ar' => $this->arabic($row['commune_name_arabic'] ?? null),
                ]
            );

            $kept[] = $commune->id;
        }

        return $kept;
    }

    /**
     * Communes the file no longer lists. Most are rows the previous seeder
     * created under a spelling of its own; some have simply changed wilaya.
     *
     * A commune that something points at is never deleted outright: the
     * references are moved to its counterpart first, and if there is none it
     * stays put and is reported. Losing an agency's address to a tidier
     * reference table would be a poor trade.
     *
     * @param  array<int, int>  $keptIds
     */
    private function retireOrphans(array $keptIds): void
    {
        $orphans = Commune::whereNotIn('id', $keptIds)->get();
        $stranded = [];

        foreach ($orphans as $orphan) {
            $replacement = Commune::whereIn('id', $keptIds)
                ->where('slug', $orphan->slug)
                ->first();

            $agencies = Agency::where('commune_id', $orphan->id)->count();
            $vehicles = Vehicle::where('pickup_commune_id', $orphan->id)->count();

            if ($replacement) {
                Agency::where('commune_id', $orphan->id)
                    ->update(['commune_id' => $replacement->id]);
                Vehicle::where('pickup_commune_id', $orphan->id)
                    ->update(['pickup_commune_id' => $replacement->id]);

                $orphan->delete();

                continue;
            }

            if ($agencies === 0 && $vehicles === 0) {
                $orphan->delete();

                continue;
            }

            $stranded[] = "{$orphan->name_fr} ({$agencies} agence(s), {$vehicles} annonce(s))";
        }

        foreach ($stranded as $line) {
            $this->command?->warn("Commune absente du fichier mais encore référencée, conservée : {$line}");
        }
    }

    /**
     * The reform moves communes between wilayas. An agency whose commune has
     * changed hands would otherwise keep pointing at its former wilaya, and the
     * form that checks the pair would refuse the agency's own address.
     */
    private function realignParents(): void
    {
        $agencies = DB::table('agencies')
            ->join('communes', 'communes.id', '=', 'agencies.commune_id')
            ->whereColumn('agencies.wilaya_id', '!=', 'communes.wilaya_id')
            ->select('agencies.id', 'communes.wilaya_id')
            ->get();

        foreach ($agencies as $row) {
            Agency::whereKey($row->id)->update(['wilaya_id' => $row->wilaya_id]);
        }

        $vehicles = DB::table('vehicles')
            ->join('communes', 'communes.id', '=', 'vehicles.pickup_commune_id')
            ->whereColumn('vehicles.pickup_wilaya_id', '!=', 'communes.wilaya_id')
            ->select('vehicles.id', 'communes.wilaya_id')
            ->get();

        foreach ($vehicles as $row) {
            Vehicle::whereKey($row->id)->update(['pickup_wilaya_id' => $row->wilaya_id]);
        }

        $moved = $agencies->count() + $vehicles->count();

        if ($moved > 0) {
            $this->command?->info("{$moved} rattachement(s) de wilaya corrigé(s) après la réforme.");
        }
    }

    /**
     * Two communes of the same wilaya may share a name in the source. The slug
     * ends up in a public URL and is unique per wilaya in the schema, so the
     * second one is suffixed rather than silently overwriting the first.
     *
     * @param  array<string, bool>  $used
     */
    private function uniqueCommuneSlug(string $name, int $wilayaId, array &$used): string
    {
        $base = Str::slug($name) ?: 'commune';
        $slug = $base;
        $suffix = 2;

        while (isset($used["{$wilayaId}:{$slug}"])) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        $used["{$wilayaId}:{$slug}"] = true;

        return $slug;
    }

    private function uniqueWilayaSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Wilaya::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Repairs Arabic that was saved as UTF-8 and re-read as Latin-1 — the
     * "Ø£Ø¯Ø±Ø§Ø±" instead of "أدرار" that a spreadsheet round-trip produces.
     * The conversion is only kept when it actually yields Arabic letters:
     * applied to a correct string it would destroy it.
     */
    private function arabic(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/\p{Arabic}/u', $value)) {
            return $value;
        }

        $repaired = @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');

        return preg_match('/\p{Arabic}/u', (string) $repaired) ? $repaired : null;
    }
}
