<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Les comptes, vus de l'administration (2026-10-18).
 *
 * Jusqu'ici on modérait des agences et des annonces, jamais des personnes :
 * un client qui multiplie les fausses réservations n'avait aucun frein.
 *
 * L'écran ne propose que deux gestes, suspendre et rouvrir. Pas de
 * suppression : les réservations portent `client_id`, et effacer un client
 * effacerait l'historique des agences qui l'ont servi. Pas de changement de
 * rôle non plus — passer quelqu'un administrateur d'un clic est le genre de
 * pouvoir qui se donne en base, lentement, pas dans une liste.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserModerationService $moderation)
    {
    }

    public function index(Request $request): Response
    {
        $filtres = $request->only(['role', 'statut', 'search']);

        $utilisateurs = User::query()
            // Sans ce chargement, la liste tire une requête par ligne pour
            // savoir si le compte tient une agence.
            ->with('agency:id,user_id,commercial_name,status')
            ->withCount('bookings')
            ->when($filtres['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filtres['statut'] ?? null, fn ($q, $statut) => $statut === 'suspendu'
                ? $q->whereNotNull('suspended_at')
                : $q->whereNull('suspended_at'))
            ->when($filtres['search'] ?? null, fn ($q, $recherche) => $q->where(
                fn ($sous) => $sous->where('name', 'like', "%{$recherche}%")
                    ->orWhere('email', 'like', "%{$recherche}%")
                    ->orWhere('phone', 'like', "%{$recherche}%")
            ))
            // Les comptes fermés d'abord : c'est la liste qu'on vient relire.
            ->orderByRaw('CASE WHEN suspended_at IS NULL THEN 1 ELSE 0 END')
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role,
                // Comment la personne est entrée : utile pour comprendre un
                // compte sans mot de passe.
                'par_google' => $u->google_id !== null,
                'verifie' => $u->email_verified_at !== null,
                'suspendu' => $u->isSuspended(),
                'motif' => $u->suspension_reason,
                'suspendu_le' => $u->suspended_at?->translatedFormat('d M Y'),
                'reservations' => $u->bookings_count,
                'agence' => $u->agency?->only(['id', 'commercial_name', 'status']),
                'inscrit_le' => $u->created_at->translatedFormat('d M Y'),
                // L'interface n'affiche pas un bouton qui serait refusé.
                'intouchable' => $u->isAdmin() || $u->id === $request->user()->id,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'utilisateurs' => $utilisateurs,
            'filtres' => $filtres,
            'roles' => [User::ROLE_CLIENT, User::ROLE_AGENCY, User::ROLE_ADMIN],
            'compteurs' => [
                'total' => User::count(),
                'suspendus' => User::whereNotNull('suspended_at')->count(),
            ],
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $valide = $request->validate([
            // Assez long pour dire quoi : le motif est montré à la personne
            // à sa prochaine tentative, et « abus » n'explique rien.
            'motif' => ['required', 'string', 'min:10', 'max:500'],
        ], [], ['motif' => 'motif']);

        try {
            $this->moderation->suspend($user, $valide['motif']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return back()->with('status', __('Le compte a été suspendu.'));
    }

    /**
     * Confirmer une adresse a la place de son titulaire.
     *
     * Une agence ne peut pas entrer tant qu'elle n'a pas clique le lien de
     * verification, et ce lien part par courriel. Tant que la production
     * tourne sans serveur d'envoi, c'est le seul moyen de debloquer un
     * dossier legitime.
     */
    public function verifyEmail(User $user): RedirectResponse
    {
        $this->moderation->verifyEmail($user);

        return back()->with('status', __('L’adresse a été marquée comme vérifiée.'));
    }

    public function reinstate(User $user): RedirectResponse
    {
        $this->moderation->reinstate($user);

        return back()->with('status', __('Le compte a été rouvert.'));
    }
}
