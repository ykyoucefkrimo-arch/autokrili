<?php

namespace App\Http\Requests\Agency;

use App\Models\PricingRule;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Serves creation and edition alike: the two forms are the same form, and
 * keeping one set of rules is what stops an edit from accepting what a
 * creation refuses.
 */
class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route already resolves the agency; ownership is checked by the
        // policy in the controller for the edit case.
        return true;
    }

    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:60'],
            // A rental fleet is not a museum: 1990 is generous, and next
            // year's model is legitimately on sale today.
            'year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],

            'category' => ['required', Rule::in(Vehicle::CATEGORIES)],
            'transmission' => ['required', Rule::in(Vehicle::TRANSMISSIONS)],
            'fuel' => ['required', Rule::in(Vehicle::FUELS)],

            'seats' => ['required', 'integer', 'min:1', 'max:60'],
            'doors' => ['required', 'integer', 'min:2', 'max:6'],
            'air_conditioning' => ['boolean'],
            'mileage_limit_per_day' => ['nullable', 'integer', 'min:20', 'max:2000'],
            'description' => ['nullable', 'string', 'max:2000'],

            'pickup_wilaya_id' => ['required', 'integer', 'exists:wilayas,id'],
            // Same guard as the registration form: a pickup point in another
            // wilaya would send the client hundreds of kilometres away.
            'pickup_commune_id' => [
                'required', 'integer',
                Rule::exists('communes', 'id')->where('wilaya_id', $this->input('pickup_wilaya_id')),
            ],

            'with_driver_available' => ['boolean'],
            'driver_price_per_day' => ['nullable', 'integer', 'min:0', 'max:200000'],

            'pricing' => ['required', 'array'],
            'pricing.daily' => ['required', 'integer', 'min:500', 'max:500000'],
            'pricing.weekly' => ['nullable', 'integer', 'min:500', 'max:3000000'],
            'pricing.monthly' => ['nullable', 'integer', 'min:500', 'max:9000000'],
        ];
    }

    /**
     * Degressive pricing has to actually be degressive. A weekly rate above
     * seven daily ones is almost always a typo, and the booking engine would
     * quietly charge the cheaper one — leaving the agency to discover the
     * mistake on the invoice.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $daily = (int) $this->input('pricing.daily');

                foreach ([PricingRule::WEEKLY, PricingRule::MONTHLY] as $type) {
                    $price = (int) $this->input("pricing.{$type}");
                    $ceiling = $daily * PricingRule::UNIT_DAYS[$type];

                    if ($price > 0 && $daily > 0 && $price > $ceiling) {
                        $validator->errors()->add(
                            "pricing.{$type}",
                            'Ce tarif doit rester inférieur au tarif journalier multiplié par '
                                .PricingRule::UNIT_DAYS[$type]." jours ({$ceiling} DA), sinon il n’offre aucune remise."
                        );
                    }
                }

                if ($this->boolean('with_driver_available') && ! $this->input('driver_price_per_day')) {
                    $validator->errors()->add(
                        'driver_price_per_day',
                        'Indiquez le prix du chauffeur par jour, ou désactivez l’option.'
                    );
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'brand' => 'marque',
            'model' => 'modèle',
            'year' => 'année',
            'category' => 'catégorie',
            'transmission' => 'boîte de vitesses',
            'fuel' => 'carburant',
            'seats' => 'nombre de places',
            'doors' => 'nombre de portes',
            'mileage_limit_per_day' => 'kilométrage autorisé par jour',
            'pickup_wilaya_id' => 'wilaya de retrait',
            'pickup_commune_id' => 'commune de retrait',
            'driver_price_per_day' => 'prix du chauffeur',
            'pricing.daily' => 'tarif journalier',
            'pricing.weekly' => 'tarif hebdomadaire',
            'pricing.monthly' => 'tarif mensuel',
        ];
    }

    public function messages(): array
    {
        return [
            'pricing.daily.required' => 'Le tarif journalier est obligatoire : c’est lui qui sert de base à tous les calculs.',
            'pickup_commune_id.exists' => 'Cette commune n’appartient pas à la wilaya de retrait sélectionnée.',
        ];
    }
}
