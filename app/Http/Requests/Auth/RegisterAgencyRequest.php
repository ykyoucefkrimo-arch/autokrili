<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterAgencyRequest extends FormRequest
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
            /*
             * Plus de registre de commerce ni de NIF depuis le 2026-10-18 :
             * trois champs de moins avant de savoir si la plateforme vaut la
             * peine. Les colonnes existent toujours pour les agences qui en
             * avaient fourni un.
             */

            'wilaya_id' => ['required', 'integer', 'exists:wilayas,id'],
            // The commune must belong to the chosen wilaya, otherwise an agency
            // could be registered in a commune of another region entirely.
            'commune_id' => [
                'required', 'integer',
                Rule::exists('communes', 'id')->where('wilaya_id', $this->input('wilaya_id')),
            ],
            'address' => ['required', 'string', 'max:255'],

            'phone' => ['required', 'string', 'max:20', $this->algerianPhoneRule()],
            'whatsapp' => ['nullable', 'string', 'max:20', $this->algerianPhoneRule()],

            /*
             * Identifiants : exiges du visiteur, ignores du connecte.
             *
             * Depuis le 2026-10-18 un client peut inscrire son agence sans se
             * deconnecter. Son compte EST le compte de l'agence ; lui
             * redemander une adresse creerait un doublon, et un mot de passe
             * n'aurait pas de sens pour quelqu'un arrive par Google, qui n'en
             * a jamais choisi.
             */
            'email' => $this->user()
                ? ['prohibited']
                : ['required', 'string', 'lowercase', 'email', 'max:150', 'unique:'.User::class],
            'password' => $this->user()
                ? ['prohibited']
                : ['required', 'confirmed', Password::defaults()],

            'description' => ['nullable', 'string', 'max:2000'],

            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /** Accepts `+213 5 XX XX XX XX` as well as the local `0X XX XX XX XX`. */
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
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Le téléphone doit être au format +213 5 55 12 34 56 ou 05 55 12 34 56.',
            'whatsapp.regex' => 'Le numéro WhatsApp doit être au format +213 5 55 12 34 56 ou 05 55 12 34 56.',
            'commune_id.exists' => 'Cette commune n’appartient pas à la wilaya sélectionnée.',
            'logo.max' => 'Le logo ne doit pas dépasser 5 Mo.',
        ];
    }
}
