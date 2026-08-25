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
    public function register(array $data, ?UploadedFile $logo, UploadedFile $tradeRegister): Agency
    {
        return DB::transaction(function () use ($data, $logo, $tradeRegister) {
            $user = User::create([
                'name' => $data['manager_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'],
                'role' => User::ROLE_AGENCY,
            ]);
            $user->syncRoles([User::ROLE_AGENCY]);

            return Agency::create([
                'user_id' => $user->id,
                'commercial_name' => $data['commercial_name'],
                'slug' => $this->uniqueSlug($data['commercial_name']),
                'manager_name' => $data['manager_name'],
                'trade_register_number' => $data['trade_register_number'],
                'nif' => $data['nif'] ?? null,
                'wilaya_id' => $data['wilaya_id'],
                'commune_id' => $data['commune_id'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'whatsapp' => $data['whatsapp'] ?? null,
                'description' => $data['description'] ?? null,
                'logo_path' => $logo ? $this->storeLogo($logo) : null,
                'trade_register_file' => $this->storeTradeRegister($tradeRegister),
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

    /**
     * The trade register is an identity document. It goes on the `local` disk,
     * outside anything the web server serves, and is readable only through a
     * policy-guarded route (specification 11).
     */
    private function storeTradeRegister(UploadedFile $file): string
    {
        return $file->store('agencies/trade-registers', 'local');
    }
}
