<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AgencyRegistrationService
{
    /**
     * Creates the owner account and its agency in one transaction. Half a
     * registration — a user without an agency, or the reverse — would leave
     * someone locked out with no way to finish.
     */
    public function register(
        array $data,
        ?UploadedFile $logo,
        ?User $proprietaire = null,
    ): Agency {
        return DB::transaction(function () use ($data, $logo, $proprietaire) {
            /*
             * Un compte deja connecte devient le compte de l'agence au lieu
             * d'en creer un second (2026-10-18). Sans cela, quelqu'un arrive
             * par Google devait se deconnecter, puis redonner une adresse —
             * et se retrouvait avec deux comptes pour une seule personne.
             *
             * Le role ne gouverne que l'affichage et la verification
             * d'adresse : promouvoir un client ne lui retire rien, et ses
             * reservations passees restent les siennes.
             */
            $user = $proprietaire ?: User::create([
                'name' => $data['manager_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'],
                'role' => User::ROLE_AGENCY,
            ]);

            if ($proprietaire) {
                $user->forceFill([
                    'role' => User::ROLE_AGENCY,
                    'phone' => $user->phone ?: $data['phone'],
                ])->save();
            }

            $user->syncRoles([User::ROLE_AGENCY]);

            return Agency::create([
                'user_id' => $user->id,
                'commercial_name' => $data['commercial_name'],
                'slug' => $this->uniqueSlug($data['commercial_name']),
                'manager_name' => $data['manager_name'],
                'wilaya_id' => $data['wilaya_id'],
                'commune_id' => $data['commune_id'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'whatsapp' => $data['whatsapp'] ?? null,
                'description' => $data['description'] ?? null,
                'logo_path' => $logo ? $this->storeLogo($logo) : null,
                'status' => Agency::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Two agencies may legitimately share a commercial name; the slug is what
     * ends up in a public URL, so it must stay unique on its own.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Agency::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /** Logos are public: they appear on every listing card. */
    private function storeLogo(UploadedFile $file): string
    {
        return $file->store('agencies/logos', 'public');
    }

    /*
     * `storeTradeRegister()` retiree le 2026-10-18 avec le champ qui
     * l'alimentait. Les documents deja deposes restent sur le disque `local`
     * et continuent d'etre servis par la route de l'administration.
     */
}
