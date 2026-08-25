# Cahier des charges — Plateforme de location de voitures en Algérie

> **Instructions pour Claude Code**
> Ce document est la spécification complète du projet. Lis-le entièrement avant d'écrire du code.
> Construis le projet **par phases** (voir §14), en t'arrêtant à la fin de chaque phase pour que je valide.
> Ne fais aucune supposition silencieuse : si un point est ambigu, pose-moi la question avant de coder.
> Tous les libellés de l'interface sont en **français**. Le code, les noms de variables et les commentaires sont en **anglais**.

---

## 1. Contexte et objectif

Créer une **marketplace** qui regroupe les agences de location de voitures en Algérie.

Trois acteurs :

| Acteur | Rôle |
|---|---|
| **Visiteur / Client** | Cherche une voiture par wilaya, commune et dates, consulte les disponibilités, réserve. |
| **Agence** | Crée un compte, souscrit une formule (Silver / Gold / Platinium), publie ses annonces de véhicules, gère son calendrier de disponibilité et ses réservations depuis un dashboard professionnel. |
| **Administrateur** | Modère les inscriptions d'agences et les annonces, attribue les formules, supervise la plateforme. |

**Le modèle économique repose sur la formule d'abonnement de l'agence**, qui détermine le nombre d'annonces, le nombre de photos et la priorité d'affichage dans les résultats de recherche.

---

## 2. Stack technique imposée

- **Backend** : Laravel 12 (PHP 8.3+)
- **Frontend** : Vue 3 (Composition API, `<script setup>`)
- **Liaison** : **Inertia.js** — pas d'API REST séparée pour le front public et les dashboards. Cela évite de maintenir deux couches d'authentification et accélère le développement.
- **CSS** : Tailwind CSS 3
- **Base de données** : MySQL 8 (ou MariaDB 10.6+)
- **Build** : Vite
- **Auth** : Laravel Breeze (stack Vue + Inertia)
- **Rôles & permissions** : `spatie/laravel-permission`
- **Images** : `intervention/image` pour le redimensionnement, stockage sur disque `public` (prévoir une bascule S3 plus tard)
- **Tests** : Pest

Packages complémentaires autorisés : `spatie/laravel-medialibrary` (galeries), `barryvdh/laravel-dompdf` (bons de réservation PDF), `maatwebsite/excel` (exports admin).

**Ne pas** utiliser de package d'admin clé en main (Filament, Nova, Voyager). Les dashboards sont construits en Vue pour garder la main sur l'UX.

---

## 3. Rôles et cycle de vie des comptes

### 3.1 Rôles

- `client` — inscription libre, active immédiatement.
- `agency` — inscription soumise à **modération administrateur**.
- `admin` — créé par seeder uniquement, jamais par formulaire public.

### 3.2 Cycle de vie d'une agence

```
inscription → statut "pending" (aucune action possible, écran d'attente)
   ↓ admin approuve
statut "approved" + formule attribuée par l'admin → accès complet au dashboard
   ↓ admin rejette
statut "rejected" + motif obligatoire → email envoyé à l'agence
   ↓ admin suspend (à tout moment)
statut "suspended" → annonces masquées du site public, dashboard en lecture seule
```

Une agence en attente qui se connecte voit un écran dédié : « Votre compte est en cours de vérification », pas le dashboard vide.

### 3.3 Informations demandées à l'inscription d'une agence

Nom commercial, nom du gérant, numéro de registre de commerce, NIF, wilaya, commune, adresse, téléphone (format `+213` ou `0X XX XX XX XX`), email, logo, et **upload obligatoire du registre de commerce** (PDF ou image) que l'administrateur consulte pour valider.

---

## 4. Les formules Silver / Gold / Platinium

### 4.1 Matrice des formules

| Caractéristique | Silver | Gold | Platinium |
|---|---|---|---|
| Nombre d'annonces actives | 5 | 20 | Illimité |
| Photos par annonce | **1** | 5 | 10 |
| Visible sur tout le répertoire | ✓ | ✓ | ✓ |
| Priorité commune | ✗ | ✓ | ✓ |
| Priorité wilaya | ✗ | ✗ | ✓ |
| Badge sur la fiche | — | Badge « Gold » | Badge « Platinium » |
| Mise en avant page d'accueil | ✗ | ✗ | ✓ |
| Statistiques | Basiques (vues, réservations) | + sources de trafic, taux de conversion | + comparaison vs moyenne wilaya, export |
| Réponse aux avis clients | ✗ | ✓ | ✓ |
| Nombre d'utilisateurs du compte | 1 | 3 | 10 |

> ⚠️ **Ces valeurs doivent être stockées en base**, dans une table `plans`, et **modifiables depuis le dashboard admin** — jamais codées en dur dans le PHP. Les seeders initialisent la matrice ci-dessus.

### 4.2 Logique de tri des résultats de recherche

C'est le cœur de la valeur des formules. L'algorithme de tri par défaut :

1. **Priorité wilaya** — si la recherche porte sur une wilaya, les annonces Platinium de cette wilaya remontent en premier.
2. **Priorité commune** — ensuite les annonces Gold et Platinium de la commune recherchée.
3. **Reste du répertoire** — toutes les autres annonces, y compris Silver, triées par pertinence puis par date de mise à jour.
4. **À l'intérieur d'un même niveau de priorité**, appliquer une **rotation équitable** (ordre pseudo-aléatoire avec seed journalière) pour qu'une même agence ne monopolise pas la première place tous les jours.

L'utilisateur peut ensuite retrier manuellement (prix croissant, note, plus récent) — **ce retri manuel ignore les priorités de formule**, c'est un choix assumé de transparence.

Toute annonce mise en avant par la formule doit porter une mention discrète « Sponsorisé » pour rester honnête vis-à-vis du client.

### 4.3 Attribution des formules

**Aucun paiement en ligne en v1.** L'agence *demande* une formule, l'administrateur l'attribue manuellement après encaissement hors plateforme (virement, versement). C'est la pratique la plus réaliste pour démarrer en Algérie.

Prévoir dans le modèle `subscriptions` : `plan_id`, `starts_at`, `ends_at`, `status`, `notes_admin`. Une commande Artisan planifiée rétrograde automatiquement en Silver toute agence dont l'abonnement a expiré, et notifie l'agence 7 jours avant l'échéance.

Le code doit être écrit de façon à ce qu'un module de paiement (**Chargily Pay** pour CIB/EDAHABIA, ou SATIM) puisse être branché plus tard sans refonte : isole la logique dans un `SubscriptionService`.

---

## 5. Modèle de données

Tables principales à créer (migrations Laravel) :

**users** — `id`, `name`, `email`, `password`, `phone`, `role`, `email_verified_at`

**agencies** — `id`, `user_id`, `commercial_name`, `manager_name`, `trade_register_number`, `nif`, `wilaya_id`, `commune_id`, `address`, `latitude`, `longitude`, `phone`, `whatsapp`, `logo_path`, `trade_register_file`, `description`, `status` (pending/approved/rejected/suspended), `rejection_reason`, `approved_at`, `average_rating`, `reviews_count`

**agency_users** — table pivot pour les comptes multi-utilisateurs (Gold/Platinium)

**plans** — `id`, `name`, `slug`, `price_dzd`, `max_listings`, `max_photos`, `has_commune_priority` (bool), `has_wilaya_priority` (bool), `has_homepage_feature` (bool), `max_users`, `can_reply_reviews` (bool), `stats_level`, `is_active`, `sort_order`

**subscriptions** — `id`, `agency_id`, `plan_id`, `starts_at`, `ends_at`, `status`, `admin_note`, `created_by`

**wilayas** — `id`, `code` (01 à 58), `name_fr`, `name_ar` — **seeder complet des 58 wilayas obligatoire**

**communes** — `id`, `wilaya_id`, `name_fr`, `name_ar` — seeder des communes principales au minimum

**vehicles** (les annonces) — `id`, `agency_id`, `brand`, `model`, `year`, `category` (citadine, berline, SUV, utilitaire, 4x4, luxe, minibus), `transmission` (manuelle/automatique), `fuel` (essence, diesel, GPL, hybride, électrique), `seats`, `doors`, `air_conditioning`, `mileage_limit_per_day`, `description`, `pickup_wilaya_id`, `pickup_commune_id`, `status` (draft/pending/published/rejected/archived), `rejection_reason`, `views_count`, `published_at`

**vehicle_photos** — `id`, `vehicle_id`, `path`, `sort_order`, `is_cover`
→ **contrainte applicative** : le nombre de photos est plafonné par la formule de l'agence (1 pour Silver).

**pricing_rules** — `id`, `vehicle_id`, `duration_type` (daily/weekly/monthly), `price_dzd`, `min_days`
→ permet les tarifs dégressifs (ex. 4 500 DA/jour, 28 000 DA/semaine).

**availability_blocks** — `id`, `vehicle_id`, `start_date`, `end_date`, `reason` (maintenance, réservé hors plateforme, indisponible)

**bookings** — `id`, `booking_reference` (ex. `DZ-2026-00147`), `vehicle_id`, `agency_id`, `client_id`, `start_date`, `end_date`, `pickup_location`, `dropoff_location`, `total_days`, `total_price_dzd`, `deposit_dzd`, `client_name`, `client_phone`, `client_email`, `driver_license_number`, `status`, `cancellation_reason`, `confirmed_at`

**reviews** — `id`, `booking_id`, `agency_id`, `client_id`, `rating` (1-5), `comment`, `agency_reply`, `status` (pending/approved/rejected)

**listing_stats** — agrégats journaliers par annonce : `date`, `vehicle_id`, `views`, `contact_clicks`, `bookings_count`

**audit_logs** — traçabilité de toute action admin : `admin_id`, `action`, `model_type`, `model_id`, `old_values`, `new_values`, `created_at`

---

## 6. Moteur de disponibilité et de réservation

C'est le point techniquement le plus délicat. Il doit être implémenté dans un service dédié (`App\Services\AvailabilityService`) et **couvert par des tests Pest**.

### 6.1 Règle de disponibilité

Un véhicule est disponible sur `[start_date, end_date]` si **aucun** des éléments suivants ne chevauche cette plage :
- une réservation de statut `pending`, `confirmed` ou `in_progress`
- un blocage manuel (`availability_blocks`)

Test de chevauchement de deux plages : `A.start <= B.end AND A.end >= B.start`.

Prévoir un **délai tampon** configurable par agence (`buffer_hours`, défaut 4 h) entre deux locations, pour le nettoyage et le contrôle du véhicule.

### 6.2 Statuts d'une réservation

```
pending  →  confirmed  →  in_progress  →  completed
   ↓            ↓
cancelled   cancelled
   ↓
 expired  (auto après 24 h sans réponse de l'agence)
```

- `pending` : demande envoyée, l'agence a **24 h** pour répondre.
- `confirmed` : l'agence a accepté ; un bon de réservation PDF est généré et envoyé par email au client.
- `in_progress` : véhicule remis au client (l'agence clique « Départ effectué »).
- `completed` : véhicule restitué ; déclenche 24 h plus tard l'invitation à laisser un avis.
- `cancelled` : motif obligatoire, côté client comme côté agence.
- `expired` : job planifié, libère automatiquement les dates.

### 6.3 Prévention des doubles réservations

Au moment de la confirmation, revérifier la disponibilité **dans une transaction avec verrou** (`lockForUpdate`) sur les réservations du véhicule. Deux clients qui confirment simultanément ne doivent jamais pouvoir réserver la même plage. Écrire un test qui simule cette concurrence.

### 6.4 Calcul du prix

`total_price = somme des jours × tarif applicable`, en appliquant automatiquement la règle tarifaire la plus avantageuse selon la durée (journalier / hebdomadaire / mensuel). Afficher le détail du calcul au client avant validation. Devise : **DZD**, formaté `45 000 DA`.

---

## 7. Site public (côté client)

### 7.1 Pages

- **Accueil** — barre de recherche principale (wilaya, commune, date de départ, date de retour), véhicules mis en avant (Platinium), catégories populaires, wilayas les plus recherchées, agences récemment approuvées.
- **Résultats de recherche** — liste + carte (Leaflet + OpenStreetMap, pas de clé API requise), filtres latéraux : catégorie, boîte, carburant, nombre de places, fourchette de prix, climatisation, note minimale, agence. Pagination. Tri conforme à §4.2.
- **Fiche véhicule** — galerie photos, caractéristiques, tarifs dégressifs, **calendrier de disponibilité** (dates prises grisées), conditions de location, encart agence avec note et avis, formulaire de réservation.
- **Fiche agence** — présentation, localisation sur carte, tous ses véhicules disponibles, avis clients.
- **Tunnel de réservation** — 3 étapes : dates et options → coordonnées et permis de conduire → récapitulatif et confirmation. Le compte client est créé automatiquement à cette étape si le visiteur n'est pas connecté.
- **Espace client** — mes réservations (à venir / passées / annulées), téléchargement du bon PDF, annulation, dépôt d'avis.
- **Pages statiques** — À propos, CGU, Politique de confidentialité, Contact, FAQ, « Inscrire mon agence ».

### 7.2 Exigences transverses

- **Mobile-first strict.** L'essentiel du trafic sera sur téléphone.
- **SEO** : URLs propres (`/location-voiture/alger/bab-ezzouar`, `/vehicule/{id}-{slug}`), balises meta dynamiques, données structurées Schema.org (`Car`, `LocalBusiness`, `AggregateRating`), sitemap XML généré automatiquement.
- **i18n** : interface français par défaut, **structure prête pour l'arabe** (fichiers de langue Laravel + support RTL dans Tailwind). L'arabe peut n'être que partiellement rempli en v1, mais l'architecture doit exister.
- Numéros de téléphone cliquables et **lien WhatsApp** direct vers l'agence (usage dominant en Algérie).

---

## 8. Dashboard agence

Accessible sur `/agence`, réservé aux agences approuvées.

**Accueil** — chiffres clés (réservations du mois, taux d'acceptation, revenus estimés, vues), demandes en attente à traiter en priorité, alerte si la formule expire dans moins de 15 jours.

**Mes véhicules** — liste avec statut de modération, création/édition d'annonce en formulaire multi-étapes, upload de photos avec **blocage explicite au-delà du quota de la formule** (message : « Votre formule Silver autorise 1 photo. Passez en Gold pour en ajouter 5. » + bouton vers la page des formules). Bouton activer/désactiver une annonce.

**Calendrier** — vue mensuelle de tous les véhicules, réservations et blocages affichés, possibilité de bloquer une plage manuellement par glisser-déposer.

**Réservations** — filtres par statut, fiche détaillée avec coordonnées du client, boutons Accepter / Refuser (motif obligatoire) / Départ effectué / Retour effectué, export CSV.

**Statistiques** — selon le niveau autorisé par la formule (§4.1) : vues par annonce, évolution sur 30 jours, taux de conversion vue → réservation, véhicules les plus demandés, périodes de forte demande. Graphiques avec Chart.js. Les blocs verrouillés par la formule sont **visibles mais floutés**, avec un appel à l'action pour monter en gamme — c'est un levier de conversion, pas une frustration si c'est bien présenté.

**Avis** — consultation, et réponse publique si la formule l'autorise.

**Mon abonnement** — formule actuelle, date d'expiration, tableau comparatif des formules, bouton « Demander un changement de formule » qui crée une demande visible par l'admin.

**Paramètres** — profil de l'agence, horaires d'ouverture, conditions de location, âge minimum du conducteur, caution demandée, utilisateurs du compte (selon quota).

---

## 9. Dashboard administrateur

Accessible sur `/admin`.

**Tableau de bord** — agences en attente, annonces en attente, réservations du jour, répartition des agences par formule, courbe des inscriptions.

**Gestion des agences** — c'est l'écran central demandé.

Un tableau listant toutes les agences avec, **sur chaque ligne** : logo, nom commercial, wilaya, date d'inscription, nombre d'annonces, statut, et **un groupe de trois boutons Silver / Gold / Platinium** permettant de changer la formule en un clic, sans quitter la page.

Comportement précis attendu :
- Le bouton de la formule active est visuellement en surbrillance (Silver gris, Gold doré, Platinium violet/noir).
- Un clic sur une autre formule ouvre une petite fenêtre de confirmation demandant la **date de fin d'abonnement** et une **note interne** (ex. « virement reçu le 12/08, 3 mois »).
- La validation met à jour l'abonnement, écrit une entrée dans `audit_logs`, et envoie un email à l'agence.
- Si la rétrogradation fait dépasser le quota d'annonces ou de photos, **ne rien supprimer** : passer les annonces excédentaires (les plus anciennes) en statut `archived` et prévenir l'agence par email de ce qui a été archivé.

Actions complémentaires sur la même ligne : voir la fiche, approuver, rejeter (motif obligatoire), suspendre, se connecter en tant que l'agence (*impersonation*, pour le support).

Filtres : statut, formule, wilaya, recherche par nom. Export Excel.

**Modération des annonces** — file d'attente des annonces en statut `pending`, prévisualisation identique au rendu public, boutons Approuver / Rejeter avec **motifs prédéfinis** (photos de mauvaise qualité, prix incohérent, informations manquantes, contenu inapproprié, doublon) + champ libre. Traitement par lot possible. Toute modification d'une annonce déjà publiée la **repasse en modération**, sauf si l'agence est marquée « de confiance » (flag `is_trusted` activable par l'admin après un historique propre).

**Modération des inscriptions** — même logique, avec consultation du registre de commerce uploadé.

**Réservations** — vue globale, en lecture seule sauf pour arbitrer un litige.

**Avis** — modération avant publication.

**Formules** — édition de la matrice du §4.1 (prix, quotas, options) sans toucher au code.

**Contenu** — gestion des pages statiques, bannières d'accueil, wilayas et communes.

**Journal d'audit** — consultation de `audit_logs`, non modifiable.

---

## 10. Notifications

Emails transactionnels (queue Laravel, driver `database` en dev) :

| Événement | Destinataire |
|---|---|
| Inscription agence reçue | Agence + admin |
| Agence approuvée / rejetée | Agence |
| Annonce approuvée / rejetée | Agence |
| Nouvelle demande de réservation | Agence |
| Réservation confirmée (+ PDF) | Client |
| Réservation refusée / annulée | Les deux parties |
| Rappel J-1 avant le départ | Client + agence |
| Invitation à laisser un avis | Client |
| Abonnement expirant sous 7 jours | Agence + admin |

Prévoir un `NotificationService` abstrait pour brancher plus tard des **SMS ou WhatsApp Business**, très efficaces en Algérie.

---

## 11. Sécurité et conformité

- Validation stricte de toutes les entrées via **Form Requests** Laravel.
- **Policies** Laravel pour chaque modèle : une agence ne doit jamais pouvoir lire ou modifier les données d'une autre agence. Tester ce point explicitement.
- Protection CSRF, XSS, injection SQL (Eloquent uniquement, pas de requête brute concaténée).
- Rate limiting sur l'authentification, la recherche et la création de réservation.
- Upload de fichiers : type MIME et extension vérifiés, taille limitée (5 Mo images, 10 Mo PDF), noms générés aléatoirement, stockage hors du dossier public exécutable pour les documents sensibles (registre de commerce accessible uniquement via une route protégée par policy).
- Mots de passe hachés (bcrypt), vérification d'email obligatoire pour les agences.
- **Loi 18-07** algérienne sur la protection des données personnelles : mention légale, finalité de la collecte, droit de suppression du compte, durée de conservation des données de réservation.
- Sauvegarde quotidienne de la base (`spatie/laravel-backup`).

---

## 12. Qualité et performance

- Requêtes optimisées : **aucune requête N+1** (utiliser `with()`, et `barryvdh/laravel-debugbar` en dev pour vérifier).
- Index de base de données sur : `vehicles.status`, `vehicles.pickup_wilaya_id`, `bookings(vehicle_id, start_date, end_date)`, `agencies.status`.
- Cache des listes de wilayas/communes et de la matrice des formules.
- Images converties en **WebP** et déclinées en 3 tailles (miniature, carte, plein écran), chargement différé (`loading="lazy"`).
- Objectif : page de résultats sous 2 secondes sur connexion 3G.

**Tests Pest attendus au minimum :**
1. Le moteur de disponibilité (chevauchements, tampon, blocages).
2. L'impossibilité de créer deux réservations confirmées qui se chevauchent.
3. Le respect des quotas de formule (annonces et photos).
4. L'algorithme de tri par priorité de formule.
5. L'isolation des données entre agences (policies).
6. Le calcul du prix avec tarifs dégressifs.

---

## 13. Livrables

- Code source Laravel + Vue organisé proprement (contrôleurs fins, logique métier dans des services).
- Migrations + **seeders** : 58 wilayas, communes principales, 3 formules, 1 compte admin, 5 agences de démonstration réparties sur les 3 formules, 25 véhicules, quelques réservations et avis.
- `README.md` : prérequis, installation, commandes, identifiants de démonstration.
- `.env.example` documenté.
- Fichier des tâches planifiées (expiration des réservations, rétrogradation des abonnements, agrégation des statistiques journalières).

---

## 14. Ordre de construction

Construis dans cet ordre et **arrête-toi à la fin de chaque phase** pour validation :

1. **Fondations** — projet Laravel + Breeze/Inertia/Vue/Tailwind, rôles, migrations complètes, seeders wilayas/communes/formules.
2. **Comptes** — inscription client, inscription agence avec modération, dashboard admin minimal pour approuver/rejeter.
3. **Annonces** — CRUD véhicules côté agence, gestion des photos avec quotas, file de modération admin.
4. **Recherche publique** — page d'accueil, recherche, filtres, fiche véhicule, algorithme de tri par formule.
5. **Réservations** — moteur de disponibilité, tunnel de réservation, calendrier agence, cycle de vie des statuts, tests de concurrence.
6. **Formules** — écran admin avec les boutons Silver/Gold/Platinium par ligne, gestion des abonnements, rétrogradation automatique, application des quotas.
7. **Statistiques et avis** — agrégats journaliers, graphiques par niveau de formule, système d'avis modéré.
8. **Finition** — emails, PDF, SEO, i18n, performances, tests, README.

---

## 15. Questions à me poser avant de démarrer

Si l'un de ces points bloque, demande-moi plutôt que de trancher seul :

- Le client doit-il payer un acompte en ligne, ou tout se règle-t-il à l'agence ? *(hypothèse retenue : tout à l'agence en v1)*
- Une agence peut-elle avoir plusieurs points de retrait dans différentes wilayas ?
- Les tarifs varient-ils selon la saison (haute/basse saison estivale) ?
- Faut-il gérer la location **avec chauffeur** comme option ?
- Le client doit-il pouvoir réserver sans créer de compte ?
