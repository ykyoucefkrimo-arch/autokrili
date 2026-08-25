# Mise en ligne sur hébergement mutualisé (Hostinger)

Ce document décrit le déploiement d'Autokrili sur un mutualisé Hostinger.
Il vise une démonstration client : les données sont celles des seeders, et
les emails ne partent pas réellement (voir « Emails »).

## Ce que le mutualisé impose

Trois contraintes façonnent tout le reste :

1. **La racine web est imposée** (`public_html`) et n'est pas déplaçable.
   L'application ne peut donc pas être déposée telle quelle : `.env`,
   `vendor/` et la base de code seraient servis par le serveur web.
2. **Pas de Composer ni de Node** côté serveur. Les dépendances et les
   assets sont donc compilés en local et expédiés déjà construits.
3. **Pas de worker de file d'attente.** Une notification différée ne
   partirait jamais : `QUEUE_CONNECTION=sync`.

## Découpage des fichiers

    ~/domains/<domaine>/
      ├── public_html/        <- racine web : contenu de public/
      │     ├── index.php     (chemins réécrits, voir plus bas)
      │     ├── build/        assets compilés par Vite
      │     └── storage/      photos des véhicules (dossier réel)
      └── autokrili_app/      <- hors racine web : tout le reste

`public_html/index.php` pointe vers le dossier voisin :

    require __DIR__.'/../autokrili_app/vendor/autoload.php';
    $app = require_once __DIR__.'/../autokrili_app/bootstrap/app.php';

## Le disque des photos, sans lien symbolique

`php artisan storage:link` crée normalement `public/storage`. Sur mutualisé
sans SSH, le gestionnaire de fichiers ne sait pas créer de lien symbolique.

`config/filesystems.php` lit donc `PUBLIC_DISK_ROOT`, un chemin relatif à la
racine du projet. Renseigné, les photos s'écrivent directement dans le
dossier servi ; absent, on retrouve le comportement habituel avec lien
symbolique. Le développement local n'est pas affecté.

    PUBLIC_DISK_ROOT=../public_html/storage

## Base de données

Créer la base dans hPanel (Bases de données → MySQL), puis importer le dump
via phpMyAdmin. Le dump contient le schéma **et** les données de
démonstration : agences, véhicules, réservations, 69 wilayas et
1541 communes. `artisan migrate --seed` n'est pas jouable sans SSH.

Reporter ensuite le nom de base, l'utilisateur et le mot de passe dans
`autokrili_app/.env`.

## Tâches planifiées

Quatre tâches tournent (`routes/console.php`), dont l'expiration des
réservations sans réponse au bout de 24 h. Sans elles, une demande reste
« en attente » indéfiniment et bloque les dates du véhicule.

Dans hPanel → Avancé → Tâches Cron, toutes les minutes :

    /usr/bin/php ~/domains/<domaine>/autokrili_app/artisan schedule:run >/dev/null 2>&1

## Emails

`MAIL_MAILER=file` : chaque email est écrit dans
`storage/app/private/mails/` et se relit dans le back office, sur
`/admin/emails`. C'est le bon réglage pour une démonstration — on montre le
contenu des messages sans dépendre d'un SMTP.

Pour des envois réels, créer une adresse dans hPanel puis basculer sur
`MAIL_MAILER=smtp` avec `smtp.hostinger.com`, port 465, SSL. L'écran
`/admin/emails` se ferme alors de lui-même (404) : une liste vide se
prendrait pour une panne d'envoi.

## Après chaque mise à jour du code

Les caches de configuration et de routes gardent les anciennes valeurs :

    php artisan config:clear && php artisan route:clear && php artisan view:clear

## Ce qui reste à faire avant un vrai lancement

- `APP_DEBUG=false` est déjà posé ; le vérifier après toute manipulation.
- Faire relire les CGU et la politique de confidentialité par un juriste.
- Remplacer les données de démonstration par les vraies agences.
- Basculer sur un vrai domaine et un certificat, puis mettre à jour `APP_URL`.
