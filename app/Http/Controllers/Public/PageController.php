<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Vehicle;
use App\Models\Wilaya;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The static pages of specification 7.1. Their content lives here rather than
 * in the Vue files: it is legal and editorial text that a lawyer may need to
 * amend, and hunting for it inside a template is not how that goes well.
 */
class PageController extends Controller
{
    public function about(): Response
    {
        return $this->render('À propos', [
            [
                'title' => 'Ce que fait Autokrili',
                'body' => "Autokrili met en relation les agences de location de voitures algériennes "
                    ."et les personnes qui cherchent un véhicule. Nous ne louons pas de voitures : "
                    ."nous rendons visibles celles des agences, et nous transmettons les demandes.",
            ],
            [
                'title' => 'Les agences sont vérifiées',
                'body' => "Chaque agence dépose son registre de commerce à l'inscription. Un "
                    ."administrateur le consulte avant d'autoriser la publication d'une seule annonce. "
                    ."Une agence dont le dossier est incomplet est refusée, avec le motif.",
            ],
            [
                'title' => 'Le règlement se fait à l’agence',
                'body' => "Il n'y a pas de paiement en ligne. Vous réservez ici, l'agence confirme, "
                    ."et vous réglez sur place au moment du départ. Le contrat de location est conclu "
                    ."entre vous et l'agence.",
            ],
        ]);
    }

    public function terms(): Response
    {
        return $this->render("Conditions générales d'utilisation", [
            [
                'title' => 'Objet',
                'body' => "Autokrili est une plateforme de mise en relation. Elle n'est pas partie au "
                    ."contrat de location, qui lie le client et l'agence. Les conditions de location, "
                    ."la caution et l'âge minimum du conducteur sont fixés par chaque agence et "
                    ."affichés sur ses annonces.",
            ],
            [
                'title' => 'Réservation',
                'body' => "Une demande de réservation n'est pas une réservation ferme. L'agence "
                    ."dispose de 24 heures pour l'accepter ou la refuser ; sans réponse, la demande "
                    ."expire et les dates sont libérées. Une réservation confirmée donne lieu à un bon "
                    ."à présenter au comptoir, avec une pièce d'identité et le permis de conduire.",
            ],
            [
                'title' => 'Annulation',
                'body' => "Le client comme l'agence peuvent annuler une réservation tant que le "
                    ."véhicule n'est pas parti, en indiquant un motif. Les éventuels frais d'annulation "
                    ."relèvent des conditions de l'agence.",
            ],
            [
                'title' => 'Obligations des agences',
                'body' => "L'agence s'engage à tenir ses annonces à jour, à honorer les réservations "
                    ."qu'elle a confirmées et à disposer des assurances exigées par la réglementation "
                    ."algérienne. Une annonce trompeuse est retirée par la modération.",
            ],
        ]);
    }

    public function privacy(): Response
    {
        return $this->render('Politique de confidentialité', [
            [
                'title' => 'Données collectées',
                'body' => "Pour un client : nom, téléphone, email éventuel et numéro de permis, "
                    ."saisis au moment d'une réservation. Pour une agence : les informations légales "
                    ."de son inscription, dont le registre de commerce.",
            ],
            [
                'title' => 'Usage',
                'body' => "Ces données servent à traiter les réservations et à permettre à l'agence "
                    ."et au client de se joindre. Le registre de commerce sert uniquement à la "
                    ."vérification par un administrateur ; il n'est jamais public.",
            ],
            [
                'title' => 'Vos droits',
                'body' => "Conformément à la loi 18-07 relative à la protection des personnes "
                    ."physiques dans le traitement des données à caractère personnel, vous pouvez "
                    ."accéder à vos données, les corriger et en demander la suppression. La suppression "
                    ."d'un compte est réelle, pas un archivage. Les réservations passées survivent "
                    ."parce qu'elles constituent une pièce comptable pour l'agence.",
            ],
            [
                'title' => 'Conservation',
                'body' => "Les données d'un compte supprimé sont effacées immédiatement. Les "
                    ."réservations sont conservées le temps exigé par les obligations comptables des "
                    ."agences.",
            ],
        ]);
    }

    public function faq(): Response
    {
        return $this->render('Questions fréquentes', [
            [
                'title' => 'Dois-je payer en ligne ?',
                'body' => "Non. Vous réglez à l'agence, au moment du départ. Autokrili ne prend aucun "
                    ."paiement et ne demande jamais de coordonnées bancaires.",
            ],
            [
                'title' => 'Faut-il créer un compte pour réserver ?',
                'body' => "Non. Le compte est ouvert automatiquement avec les informations de votre "
                    ."demande, et vous y retrouvez vos réservations et vos bons.",
            ],
            [
                'title' => 'Combien de temps pour avoir une réponse ?',
                'body' => "L'agence a 24 heures. Passé ce délai, la demande expire d'elle-même et vos "
                    ."dates sont libérées : vous n'attendez jamais indéfiniment.",
            ],
            [
                'title' => 'Puis-je annuler ?',
                'body' => "Oui, tant que le véhicule n'est pas parti, depuis « Mes réservations ». "
                    ."Un motif est demandé : l'agence planifie avec.",
            ],
            [
                'title' => 'Comment inscrire mon agence ?',
                'body' => "Par le formulaire « Inscrire mon agence ». Prévoyez votre registre de "
                    ."commerce : il est obligatoire, et vérifié avant toute publication. L'inscription "
                    ."est gratuite.",
            ],
            [
                'title' => 'Que veulent dire les badges Gold et Platinium ?',
                'body' => "Ce sont les formules d'abonnement des agences. Elles déterminent le nombre "
                    ."d'annonces, de photos et la place dans les résultats. Une annonce remontée par la "
                    ."formule de son agence porte la mention « Sponsorisé ».",
            ],
        ]);
    }

    public function contact(): Response
    {
        return $this->render('Contact', [
            [
                'title' => 'Une question sur une réservation',
                'body' => "Adressez-vous d'abord à l'agence : son téléphone et son WhatsApp figurent "
                    ."sur la réservation et sur sa fiche. C'est elle qui détient le véhicule et les "
                    ."clés.",
            ],
            [
                'title' => 'Un problème avec la plateforme',
                'body' => "Écrivez à contact@autokrili.dz en indiquant votre référence de réservation "
                    ."si vous en avez une. Nous répondons sous deux jours ouvrés.",
            ],
            [
                'title' => 'Signaler une annonce',
                'body' => "Une annonce trompeuse, un prix incohérent, une photo qui n'est pas celle du "
                    ."véhicule : écrivez à moderation@autokrili.dz avec le lien de l'annonce. Chaque "
                    ."signalement est examiné.",
            ],
        ]);
    }

    /** @param  array<int, array{title: string, body: string}>  $sections */
    private function render(string $title, array $sections): Response
    {
        return Inertia::render('Public/Page', [
            'title' => $title,
            'sections' => $sections,
            // Le pied de page de ces pages sert de porte d'entrée au catalogue :
            // une page statique sans issue est une page qu'on quitte.
            'stats' => [
                'vehicles' => Vehicle::visible()->count(),
                'agencies' => Agency::where('status', Agency::STATUS_APPROVED)->count(),
                'wilayas' => Wilaya::count(),
            ],
        ]);
    }
}
