<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // `today` and not `now`: a rental starting this afternoon is booked
            // this morning, and refusing it would be absurd.
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            // Six months out: beyond that an agency cannot honestly commit.
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before:+6 months'],

            'client_name' => ['required', 'string', 'max:150'],
            'client_phone' => ['required', 'string', 'max:20', $this->algerianPhoneRule()],
            // Optional: an agency may take a booking from someone without an
            // email, and the details are copied onto the booking anyway.
            'client_email' => ['nullable', 'email', 'max:150'],
            'driver_license_number' => ['nullable', 'string', 'max:50'],
            'client_note' => ['nullable', 'string', 'max:1000'],

            'with_driver' => ['boolean'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'dropoff_location' => ['nullable', 'string', 'max:255'],

            // The client ticks the agency's conditions before committing: the
            // deposit and the minimum driver age are on that screen.
            'accepts_conditions' => ['accepted'],
        ];
    }

    private function algerianPhoneRule(): string
    {
        return 'regex:/^(\+213[\s.-]?[5-7]|0[5-7])([\s.-]?\d{2}){4}$/';
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'date de départ',
            'end_date' => 'date de retour',
            'client_name' => 'nom',
            'client_phone' => 'téléphone',
            'client_email' => 'email',
            'driver_license_number' => 'numéro de permis',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => 'La date de départ ne peut pas être dans le passé.',
            'end_date.before' => 'Les réservations sont possibles jusqu’à six mois à l’avance.',
            'client_phone.regex' => 'Le téléphone doit être au format +213 5 55 12 34 56 ou 05 55 12 34 56.',
            'accepts_conditions.accepted' => 'Vous devez accepter les conditions de location de l’agence.',
        ];
    }
}
