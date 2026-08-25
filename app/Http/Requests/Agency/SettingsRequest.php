<?php

namespace App\Http\Requests\Agency;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The agency's own profile (specification 8, "Paramètres"). Everything here is
 * already displayed to clients — conditions, deposit, minimum age — and until
 * now only a seeder could fill it.
 */
class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commercial_name' => ['required', 'string', 'max:150'],
            'manager_name' => ['required', 'string', 'max:150'],
            'wilaya_id' => ['required', 'integer', 'exists:wilayas,id'],
            'commune_id' => [
                'required', 'integer',
                Rule::exists('communes', 'id')->where('wilaya_id', $this->input('wilaya_id')),
            ],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', $this->algerianPhoneRule()],
            'whatsapp' => ['nullable', 'string', 'max:20', $this->algerianPhoneRule()],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            // Bornes de l'Algérie, généreusement arrondies : une coordonnée
            // hors du pays est une erreur de saisie, pas une agence saharienne.
            'latitude' => ['nullable', 'numeric', 'between:18,38'],
            'longitude' => ['nullable', 'numeric', 'between:-9,12'],

            'rental_conditions' => ['nullable', 'string', 'max:3000'],
            'min_driver_age' => ['required', 'integer', 'min:18', 'max:80'],
            'default_deposit_dzd' => ['required', 'integer', 'min:0', 'max:2000000'],
            // Le tampon entre deux locations : au-delà de trois jours ce n'est
            // plus du nettoyage, c'est une indisponibilité à déclarer.
            'buffer_hours' => ['required', 'integer', 'min:0', 'max:72'],

            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*.closed' => ['boolean'],
            'opening_hours.*.from' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.to' => ['nullable', 'date_format:H:i'],
        ];
    }

    private function algerianPhoneRule(): string
    {
        return 'regex:/^(\+213[\s.-]?[5-7]|0[5-7])([\s.-]?\d{2}){4}$/';
    }

    public function attributes(): array
    {
        return [
            'commercial_name' => 'nom commercial',
            'manager_name' => 'nom du gérant',
            'wilaya_id' => 'wilaya',
            'commune_id' => 'commune',
            'min_driver_age' => 'âge minimum du conducteur',
            'default_deposit_dzd' => 'caution',
            'buffer_hours' => 'délai entre deux locations',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Le téléphone doit être au format +213 5 55 12 34 56 ou 05 55 12 34 56.',
            'whatsapp.regex' => 'Le numéro WhatsApp doit être au format +213 5 55 12 34 56 ou 05 55 12 34 56.',
            'commune_id.exists' => 'Cette commune n’appartient pas à la wilaya sélectionnée.',
            'latitude.between' => 'Ce point est hors d’Algérie : replacez le marqueur sur la carte.',
            'longitude.between' => 'Ce point est hors d’Algérie : replacez le marqueur sur la carte.',
        ];
    }
}
