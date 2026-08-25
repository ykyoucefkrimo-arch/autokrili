# Autokrili

Marketplace de location de voitures en Algérie : les agences publient leurs
véhicules, les visiteurs cherchent par wilaya et par dates, l'administrateur
modère et attribue les formules d'abonnement.

Ce dépôt suit le [cahier des charges](CAHIER_DES_CHARGES.md), construit par
phases. **Phase 1 (Fondations) terminée.**

---

## Prérequis

| Outil | Version | Remarque |
|---|---|---|
| PHP | **8.4.15** | `D:\wamp64\bin\php\php8.4.15` sur ce poste |
| Composer | 2.9+ | |
| Node | 20+ | |
| MySQL | 8.0+ | ou MariaDB 10.6+ |

> ⚠️ Le PHP par défaut du PATH est en 8.2, et le PHP 8.3 livré avec WAMP est un
> binaire vide, donc inutilisable. Les dépendances installées exigent PHP 8.4 :
> placez-le en tête du PATH avant toute commande.
>
> ```powershell
> $env:PATH = "D:\wamp64\bin\php\php8.4.15;" + $env:PATH
> ```

Extensions requises : `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
`ctype`, `fileinfo`, `gd`, `zip`, `curl`, `bcmath`.

---

## Installation

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Créez la base — l'encodage n'est pas optionnel, les noms arabes des wilayas en
dépendent :

```sql
CREATE DATABASE autokrili CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Puis :

```bash
php artisan migrate --seed
php artisan storage:link
```

---

## Lancer le projet

```bash
php artisan serve     # http://localhost:8000
npm run dev           # compilation des assets, dans un second terminal
```

Pour servir sous WAMP, créez un **virtual host** pointant sur le dossier
`public/`. N'ajoutez jamais de sous-dossier dans `APP_URL` : Laravel
interpréterait ce préfixe comme faisant partie de la route et toutes les pages
répondraient 404.

---

## Identifiants de démonstration

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | `admin@autokrili.dz` | `password` |

Les agences et clients de démonstration arriveront à la phase 2.

---

## Commandes utiles

```bash
php artisan test              # suite Pest
php artisan migrate:fresh --seed
php artisan route:list
npm run build                 # assets de production
```

---

## Ce que contient la phase 1

**Socle** — Laravel 12, Breeze (Vue 3 + Inertia + Tailwind), Pest,
`spatie/laravel-permission`, `intervention/image`, `dompdf`, `debugbar`.

**Schéma complet** — 12 migrations couvrant les tables du §5 du cahier des
charges : utilisateurs, wilayas, communes, formules, agences et comptes
multi-utilisateurs, abonnements et demandes de changement, véhicules, photos,
règles tarifaires, blocages de disponibilité, réservations, avis, statistiques
journalières et journal d'audit.

**Données de référence** — les **58 wilayas** (y compris les 10 créées en 2019)
avec leurs noms arabes et leurs coordonnées, **146 communes**, les trois
formules Silver / Gold / Platinium, les rôles et permissions, le compte
administrateur.

### Décisions prises pendant la construction

**Points de retrait** — portés par l'annonce (`pickup_wilaya_id`,
`pickup_commune_id`). Une agence multi-sites publie ses annonces dans des
communes différentes ; pas de table de sites.

**Chauffeur** — option payante sur l'annonce (`with_driver_available`,
`driver_price_per_day`), pas une entité à part : un second moteur de
disponibilité à croiser aurait coûté cher pour un besoin ponctuel.

**Tarifs saisonniers** — non retenus en v1. Seuls les tarifs dégressifs par
durée existent ; `pricing_rules` pourra accueillir des bornes de dates sans
refonte.

**Illimité** — `plans.max_listings` vaut `NULL` pour Platinium, jamais un grand
nombre : une sentinelle finit toujours par s'afficher quelque part, ou par
plafonner quelqu'un silencieusement.

**Suppression de compte** — réelle, pas un archivage. La loi 18-07 accorde un
droit de suppression, et une ligne masquée conserverait les données qu'elle
promettait d'effacer. Les réservations survivent parce qu'elles portent leur
propre copie du nom et du téléphone du client.

**Vérification d'email** — exigée des agences seulement. L'imposer à un client
avant sa première réservation coûterait des réservations sans rien apporter.

**Réservations et véhicules supprimés** — une réservation garde sa trace même si
l'annonce disparaît (`vehicle_id` passe à `NULL`) : c'est une pièce comptable,
pas une vue sur le catalogue.

---

## Phases suivantes

2. Comptes — inscription client et agence, modération admin
3. Annonces — CRUD véhicules, photos et quotas, file de modération
4. Recherche publique — accueil, filtres, fiche véhicule, tri par formule
5. Réservations — moteur de disponibilité, tunnel, calendrier agence
6. Formules — écran admin Silver/Gold/Platinium, rétrogradation automatique
7. Statistiques et avis
8. Finition — emails, PDF, SEO, i18n, performances

---

## Mise en production

### Emails en démonstration

`MAIL_MAILER=file` écrit chaque email dans `storage/app/private/mails` au lieu
de l'envoyer, et le back office les affiche sous **Emails** — tels que le
destinataire les aurait reçus. L'écran se ferme de lui-même dès que le mailer
n'est plus `file`.

`QUEUE_CONNECTION=sync` en démonstration : les notifications partent
immédiatement, sans worker à surveiller. **C'est le piège classique** — avec
`database` et aucun `queue:work`, les notifications restent dans la table
`jobs` et rien n'est envoyé, sans le moindre message d'erreur.

### Emails en production

Les notifications sont mises en file (`QUEUE_CONNECTION=database`, table `jobs`
déjà migrée). En développement `MAIL_MAILER=log` : les emails atterrissent dans
`storage/logs`. En production, renseigner un SMTP :

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-hebergeur.dz
MAIL_PORT=587
MAIL_USERNAME=contact@autokrili.dz
MAIL_PASSWORD=…
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="contact@autokrili.dz"
```

**Un worker est indispensable**, sinon aucune notification ne part :

```bash
php artisan queue:work --tries=3
```

### Tâches planifiées

Une seule entrée cron suffit, Laravel répartit le reste :

```cron
* * * * * cd /chemin/vers/Autokrili && php artisan schedule:run >> /dev/null 2>&1
```

Elle pilote quatre commandes : `bookings:expire` (horaire),
`bookings:remind` (17 h), `reviews:invite` (horaire) et
`subscriptions:refresh` (6 h).

### SMS et WhatsApp

Le §10 prévoit de brancher un opérateur plus tard. Tout est en place :
implémenter `App\Contracts\SmsGateway`, la lier dans un service provider, puis
`SMS_ENABLED=true`. Les canaux par notification se règlent dans
`config/notifications.php` — aucun code à reprendre.

### Langues

Français par défaut, arabe disponible (`lang/fr.json`, `lang/ar.json`, bascule
en session, `dir="rtl"` automatique). L'arabe couvre le site public ; les
espaces agence et administration restent en français.
