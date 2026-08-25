<?php

namespace Database\Seeders;

use App\Models\Commune;
use App\Models\Wilaya;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The 58 wilayas of Algeria, including the 10 created by the 2019 territorial
 * reform (codes 49 to 58). Coordinates point at the chef-lieu and feed the map.
 *
 * Every wilaya receives at least its chef-lieu commune: an agency must always
 * have a commune to register in, and a search must never hit an empty list.
 */
class WilayaSeeder extends Seeder
{
    /** [code, name_fr, name_ar, latitude, longitude] */
    private const WILAYAS = [
        ['01', 'Adrar', 'أدرار', 27.8743, -0.2939],
        ['02', 'Chlef', 'الشلف', 36.1653, 1.3345],
        ['03', 'Laghouat', 'الأغواط', 33.8000, 2.8650],
        ['04', 'Oum El Bouaghi', 'أم البواقي', 35.8775, 7.1135],
        ['05', 'Batna', 'باتنة', 35.5560, 6.1741],
        ['06', 'Béjaïa', 'بجاية', 36.7509, 5.0567],
        ['07', 'Biskra', 'بسكرة', 34.8500, 5.7280],
        ['08', 'Béchar', 'بشار', 31.6167, -2.2167],
        ['09', 'Blida', 'البليدة', 36.4700, 2.8300],
        ['10', 'Bouira', 'البويرة', 36.3736, 3.9020],
        ['11', 'Tamanrasset', 'تمنراست', 22.7850, 5.5228],
        ['12', 'Tébessa', 'تبسة', 35.4042, 8.1242],
        ['13', 'Tlemcen', 'تلمسان', 34.8783, -1.3150],
        ['14', 'Tiaret', 'تيارت', 35.3711, 1.3170],
        ['15', 'Tizi Ouzou', 'تيزي وزو', 36.7118, 4.0458],
        ['16', 'Alger', 'الجزائر', 36.7538, 3.0588],
        ['17', 'Djelfa', 'الجلفة', 34.6703, 3.2630],
        ['18', 'Jijel', 'جيجل', 36.8190, 5.7667],
        ['19', 'Sétif', 'سطيف', 36.1898, 5.4108],
        ['20', 'Saïda', 'سعيدة', 34.8303, 0.1517],
        ['21', 'Skikda', 'سكيكدة', 36.8790, 6.9065],
        ['22', 'Sidi Bel Abbès', 'سيدي بلعباس', 35.1899, -0.6308],
        ['23', 'Annaba', 'عنابة', 36.9000, 7.7667],
        ['24', 'Guelma', 'قالمة', 36.4620, 7.4260],
        ['25', 'Constantine', 'قسنطينة', 36.3650, 6.6147],
        ['26', 'Médéa', 'المدية', 36.2675, 2.7539],
        ['27', 'Mostaganem', 'مستغانم', 35.9315, 0.0892],
        ['28', "M'Sila", 'المسيلة', 35.7050, 4.5420],
        ['29', 'Mascara', 'معسكر', 35.3968, 0.1400],
        ['30', 'Ouargla', 'ورقلة', 31.9527, 5.3335],
        ['31', 'Oran', 'وهران', 35.6971, -0.6308],
        ['32', 'El Bayadh', 'البيض', 33.6800, 1.0200],
        ['33', 'Illizi', 'إليزي', 26.5041, 8.4736],
        ['34', 'Bordj Bou Arreridj', 'برج بوعريريج', 36.0731, 4.7610],
        ['35', 'Boumerdès', 'بومرداس', 36.7664, 3.4772],
        ['36', 'El Tarf', 'الطارف', 36.7672, 8.3139],
        ['37', 'Tindouf', 'تندوف', 27.6742, -8.1478],
        ['38', 'Tissemsilt', 'تيسمسيلت', 35.6072, 1.8111],
        ['39', 'El Oued', 'الوادي', 33.3683, 6.8674],
        ['40', 'Khenchela', 'خنشلة', 35.4361, 7.1436],
        ['41', 'Souk Ahras', 'سوق أهراس', 36.2864, 7.9511],
        ['42', 'Tipaza', 'تيبازة', 36.5894, 2.4483],
        ['43', 'Mila', 'ميلة', 36.4503, 6.2644],
        ['44', 'Aïn Defla', 'عين الدفلى', 36.2639, 1.9678],
        ['45', 'Naâma', 'النعامة', 33.2667, -0.3167],
        ['46', 'Aïn Témouchent', 'عين تموشنت', 35.2983, -1.1408],
        ['47', 'Ghardaïa', 'غرداية', 32.4900, 3.6700],
        ['48', 'Relizane', 'غليزان', 35.7372, 0.5561],
        ['49', 'Timimoun', 'تيميمون', 29.2639, 0.2306],
        ['50', 'Bordj Badji Mokhtar', 'برج باجي مختار', 21.3286, 0.9556],
        ['51', 'Ouled Djellal', 'أولاد جلال', 34.4167, 5.0667],
        ['52', 'Béni Abbès', 'بني عباس', 30.1300, -2.1700],
        ['53', 'In Salah', 'عين صالح', 27.1958, 2.4803],
        ['54', 'In Guezzam', 'عين قزام', 19.5686, 5.7722],
        ['55', 'Touggourt', 'تقرت', 33.1000, 6.0667],
        ['56', 'Djanet', 'جانت', 24.5542, 9.4844],
        ['57', "El M'Ghair", 'المغير', 33.9500, 5.9167],
        ['58', 'El Meniaa', 'المنيعة', 30.5833, 2.8833],
    ];

    /**
     * Communes beyond the chef-lieu, for the wilayas where rental demand is
     * concentrated. Elsewhere the chef-lieu alone is enough to start.
     */
    private const COMMUNES = [
        '16' => ['Alger Centre', 'Bab Ezzouar', 'Hydra', 'El Biar', 'Dar El Beïda',
            'Bir Mourad Raïs', 'Kouba', 'Hussein Dey', 'Bab El Oued', 'Chéraga',
            'Draria', 'Zeralda', 'Bordj El Kiffan', 'Rouiba', 'Baraki', 'Réghaïa'],
        '31' => ['Oran', 'Bir El Djir', 'Es Sénia', 'Arzew', 'Aïn Turk',
            'Gdyel', 'Bethioua', 'Mers El Kébir'],
        '25' => ['Constantine', 'El Khroub', 'Aïn Smara', 'Hamma Bouziane', 'Didouche Mourad'],
        '23' => ['Annaba', 'El Bouni', 'Sidi Amar', 'El Hadjar', 'Seraïdi'],
        '19' => ['Sétif', 'El Eulma', 'Aïn Arnat', 'Bougaa', 'Aïn Oulmene'],
        '09' => ['Blida', 'Boufarik', 'Bouinan', 'Larbaâ', 'Ouled Yaïch', 'Meftah'],
        '15' => ['Tizi Ouzou', 'Azazga', 'Draâ Ben Khedda', 'Tigzirt', 'Larbaâ Nath Irathen'],
        '06' => ['Béjaïa', 'Akbou', 'El Kseur', 'Tichy', 'Aokas', 'Souk El Ténine'],
        '13' => ['Tlemcen', 'Mansourah', 'Chetouane', 'Maghnia', 'Ghazaouet'],
        '35' => ['Boumerdès', 'Boudouaou', 'Dellys', 'Zemmouri', 'Bordj Menaiel'],
        '42' => ['Tipaza', 'Cherchell', 'Koléa', 'Hadjout', 'Bou Ismaïl'],
        '05' => ['Batna', 'Merouana', 'Arris', 'Timgad', 'Tazoult'],
        '30' => ['Ouargla', 'Hassi Messaoud', 'Rouissat', 'N’Goussa'],
        '07' => ['Biskra', 'Tolga', 'Sidi Okba', 'Chetma'],
        '47' => ['Ghardaïa', 'Metlili', 'Berriane', 'El Atteuf'],
        '21' => ['Skikda', 'Collo', 'Azzaba', 'El Arrouch'],
        '18' => ['Jijel', 'Taher', 'El Milia', 'Ziama Mansouriah'],
        '27' => ['Mostaganem', 'Aïn Tédlès', 'Sidi Ali', 'Hassi Mameche'],
        '22' => ['Sidi Bel Abbès', 'Télagh', 'Sfisef', 'Ben Badis'],
        '39' => ['El Oued', 'Guemar', 'Debila', 'Robbah'],
    ];

    public function run(): void
    {
        foreach (self::WILAYAS as [$code, $nameFr, $nameAr, $lat, $lng]) {
            $wilaya = Wilaya::updateOrCreate(
                ['code' => $code],
                [
                    'name_fr' => $nameFr,
                    'name_ar' => $nameAr,
                    'slug' => Str::slug($nameFr),
                    'latitude' => $lat,
                    'longitude' => $lng,
                ]
            );

            // The chef-lieu carries the wilaya's own name and coordinates.
            $names = self::COMMUNES[$code] ?? [$nameFr];
            if (! in_array($nameFr, $names, true)) {
                array_unshift($names, $nameFr);
            }

            foreach ($names as $name) {
                Commune::updateOrCreate(
                    ['wilaya_id' => $wilaya->id, 'slug' => Str::slug($name)],
                    [
                        'name_fr' => $name,
                        // Only the chef-lieu has a verified Arabic name for now;
                        // the rest are filled in as the Arabic locale is completed.
                        'name_ar' => $name === $nameFr ? $nameAr : null,
                        'latitude' => $name === $nameFr ? $lat : null,
                        'longitude' => $name === $nameFr ? $lng : null,
                    ]
                );
            }
        }
    }
}
