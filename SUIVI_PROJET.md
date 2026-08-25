# Autokrili — suivi du projet

Ce fichier est le point d'entrée pour reprendre le projet. Il dit où on en est,
ce qui a été décidé et pourquoi, et ce qui reste à faire.

- **Spécification** : [CAHIER_DES_CHARGES.md](CAHIER_DES_CHARGES.md)
- **Installation** : [README.md](README.md)
- **Dernière mise à jour** : 20 août 2026

---

## Avancement

| Phase | État | Contenu |
|---|---|---|
| 1 · Fondations | ✅ **Terminée** | Laravel 12, Breeze Vue/Inertia/Tailwind, schéma complet, 69 wilayas et 1541 communes |
| 2 · Comptes | ✅ **Terminée** | Inscription client et agence, modération admin, écran d'attente |
| 3 · Annonces | ✅ **Terminée** | CRUD véhicules, photos et quotas, file de modération |
| 4 · Recherche publique | 🟡 **Quasi terminée** | Accueil, recherche, fiches véhicule et agence, SEO, sitemap, pages statiques ; carte des résultats restante |
| 5 · Réservations | ✅ **Terminée** | Moteur de disponibilité, tunnel, calendrier agence, bon PDF |
| 6 · Formules | ✅ **Terminée** | Écran admin de la matrice, attribution, demandes, rétrogradation |
| 7 · Statistiques et avis | ✅ **Terminée** | Agrégats journaliers, graphiques Chart.js, avis modérés |
| 8 · Finition | 🟡 **En cours** | Canaux, rappel J-1, i18n arabe complète, cache, budget de requêtes |

**Tests : 230 verts** (`php artisan test`), plus un parcours navigateur complet
sur les phases 2 et 3.

---

## Environnement

Trois particularités de ce poste, à connaître avant toute commande :

**PHP 8.4.15 obligatoire.** Le PHP du PATH est en 8.2, et le PHP 8.3 livré avec
WAMP est un binaire de 0 octet, inutilisable. Les dépendances installées exigent
8.4 :

```powershell
$env:PATH = "D:\wamp64\bin\php\php8.4.15;" + $env:PATH
```

**MySQL forcé en InnoDB.** Le serveur WAMP est configuré sur MyISAM par défaut,
qui plafonne les index à 1000 octets *et ignore les clés étrangères sans rien
dire*. `config/database.php` force InnoDB — la correction voyage avec le projet
plutôt que de dépendre du serveur. Vérifié : 29 tables InnoDB, 32 clés
étrangères réelles.

**`APP_URL` sans sous-dossier.** Un préfixe de chemin y est interprété comme
faisant partie de la route et fait répondre 404 à toutes les pages. Pour servir
sous WAMP, créer un virtual host pointant sur `public/`.

---

## Découpage administratif — 69 wilayas, 1541 communes

Le cahier des charges parle de 58 wilayas ; la base en porte désormais **69**,
importées du fichier client `database/data/cities.json` (réforme 2025), avec
les **1541 communes** du pays et leur nom arabe.

**L'import ne reconstruit jamais les tables.** Les agences et les annonces
pointent sur des identifiants de commune : un import qui les renumérote
déplacerait en silence toutes les agences du pays. `AlgeriaDivisionsSeeder`
apparie, met à jour, puis complète.

**Les communes qui changent de wilaya sont suivies.** La réforme déplace des
communes (Barika quitte Batna, Aflou quitte Laghouat). Le seeder repointe les
références vers la commune d'accueil, puis réaligne la wilaya de l'agence ou de
l'annonce : sans cela le formulaire refuserait à une agence sa propre adresse,
puisqu'il vérifie que la commune appartient bien à la wilaya.

**Une commune référencée n'est jamais supprimée sans remplaçante.** Si le
fichier ne la connaît pas et que rien ne lui correspond, elle est conservée et
signalée en fin d'import. Perdre l'adresse d'une agence pour une table de
référence plus propre serait un mauvais échange.

**`WilayaSeeder` reste l'autorité sur les 58 wilayas historiques** : le fichier
écrit « Bejaia » et « Setif », et un slug public qui perd ses accents est une
URL qui change sous les pieds du visiteur. Il garde aussi les coordonnées des
chefs-lieux, absentes du fichier. Les onze nouvelles wilayas reçoivent leur
nom français accentué, leur nom arabe et des coordonnées approximatives,
écrits à la main dans `AlgeriaDivisionsSeeder`.

**Trois erreurs de l'ancienne liste écrite à la main ont été corrigées** au
passage : Réghaïa était rattachée à Boumerdès au lieu d'Alger, Barika à Batna
et Ouled Djellal à Biskra. Les orthographes du seeder sont maintenant alignées
sur celles du fichier — un écart y crée un doublon de la même commune.

**Les communes ne voyagent plus avec les pages.** 1541 communes livrées avec
chaque page ajoutaient environ 90 Ko au premier affichage, pour une liste dont
le visiteur ne déroule qu'une wilaya — inacceptable sous la contrainte
« mobile-first strict » du §7.2. Elles se chargent à la wilaya choisie, par
`GET /communes/{wilaya}`, mises en cache un jour côté serveur comme côté
navigateur. La charge utile de l'accueil est retombée à ~12 Ko.

**L'arabe du fichier est repris tel quel**, avec un filet : un nom arrivé en
mojibake (`Ø£Ø¯Ø±Ø§Ø±` au lieu de `أدرار`, séquelle d'un aller-retour tableur)
est réparé, mais uniquement si la conversion produit réellement des lettres
arabes — appliquée à une chaîne correcte, elle la détruirait.

### Commandes

```powershell
php artisan db:seed --class=AlgeriaDivisionsSeeder   # réimport sans rien casser
```

---

## Décisions prises

Les questions du §15 du cahier des charges ont été tranchées avec le client :

| Question | Décision |
|---|---|
| Acompte en ligne | **Non** — tout se règle à l'agence en v1 |
| Points de retrait multiples | **Portés par l'annonce** (`pickup_wilaya_id`, `pickup_commune_id`), pas de table de sites |
| Tarifs saisonniers | **Non en v1** — seulement les tarifs dégressifs par durée |
| Location avec chauffeur | **Option payante sur l'annonce** (`with_driver_available`, `driver_price_per_day`) |
| Réservation sans compte | **Compte créé automatiquement** au tunnel de réservation |

Décisions techniques prises en construisant :

**`plans.max_listings` vaut `NULL` pour l'illimité**, jamais un grand nombre :
une sentinelle finit toujours par s'afficher quelque part, ou par plafonner
quelqu'un en silence.

**La suppression de compte est réelle**, pas un archivage. La loi 18-07 accorde
un droit de suppression et une ligne masquée conserverait les données qu'elle
promet d'effacer. Les réservations survivent parce qu'elles portent leur propre
copie du nom et du téléphone du client.

**La vérification d'email ne concerne que les agences.** Elle est implémentée en
surchargeant `hasVerifiedEmail()` et non par une méthode séparée : le middleware
`verified` et l'écouteur d'inscription appellent tous deux cette méthode, un
prédicat à côté aurait été ignoré et tous les clients auraient reçu une demande
de confirmation.

**Une réservation survit à son véhicule** (`vehicle_id` passe à `NULL`) : c'est
une pièce comptable, pas une vue sur le catalogue.

**Le registre de commerce vit sur le disque `local`**, hors de tout dossier
servi par le serveur web, et transite par une route protégée par policy.

**Le back office répond 404, pas 403**, à qui n'est pas administrateur :
l'existence d'une administration n'a pas à être confirmée à un inconnu.

**Une agence approuvée reçoit toujours une formule.** Sans abonnement actif elle
retombe sur Silver : chaque contrôle de quota pose une question et doit obtenir
une réponse.

---

## Phase 1 — Fondations

**Socle** : Laravel 12.66, Breeze (Vue 3 + Inertia + Tailwind), Pest,
`spatie/laravel-permission`, `intervention/image`, `dompdf`, `debugbar`.

**Schéma** : 12 migrations, 29 tables. Utilisateurs, wilayas, communes,
formules, agences et comptes multi-utilisateurs, abonnements et demandes de
changement, véhicules, photos, règles tarifaires, blocages de disponibilité,
réservations, avis, statistiques journalières, journal d'audit.

**Données de référence** : 58 wilayas (dont les 10 créées en 2019) avec noms
arabes et coordonnées, 146 communes, 3 formules, rôles et permissions, compte
administrateur.

---

## Phase 2 — Comptes

### Ce qui existe

**Inscription client** — `/register`. Rôle `client`, téléphone au format
algérien facultatif, aucune vérification d'email.

**Inscription agence** — `/inscription-agence`. Formulaire complet, wilaya et
commune liées (la commune est vérifiée comme appartenant à la wilaya, côté
serveur comme côté navigateur), upload du registre de commerce obligatoire,
logo facultatif. Le compte part en statut `pending`. Limité à 6 tentatives par
minute : ce formulaire crée un compte et écrit deux fichiers.

**Écran d'attente** — `/agence/en-attente`. Trois états : en vérification,
rejetée avec le motif affiché, suspendue. Une agence non approuvée y est
renvoyée si elle tente d'atteindre son tableau de bord.

**Dashboard administrateur** — `/admin`. File d'attente mise en avant,
répartition par formule, dernières inscriptions.

**Gestion des agences** — `/admin/agences`. Liste filtrable (statut, wilaya,
recherche sur nom, gérant et registre de commerce), les agences en attente
remontent en premier. Approuver, rejeter, suspendre, réactiver, marquer de
confiance. Le motif est obligatoire pour rejeter et suspendre, côté serveur
comme dans la fenêtre de confirmation.

**Fiche agence** — informations légales, contact, consultation du registre de
commerce par route protégée.

### Structure du code

```
app/Services/
  AgencyRegistrationService   création compte + agence en une transaction
  AgencyModerationService     approuver / rejeter / suspendre / réactiver
  SubscriptionService         attribution des formules — point de branchement
                              d'un futur module de paiement
  AuditLogger                 journal des décisions administratives

app/Http/Middleware/
  EnsureUserIsAdmin           404 pour les non-administrateurs
  EnsureAgencyIsApproved      redirige vers l'écran d'attente ; laisse la
                              lecture seule aux agences suspendues

app/Policies/AgencyPolicy     isolation entre agences, accès au registre
app/Notifications/            inscription, approbation, rejet, suspension
```

### Comptes de démonstration

Tous avec le mot de passe `password`.

| Rôle | Email | État |
|---|---|---|
| Administrateur | `admin@autokrili.dz` | — |
| Agence Platinium | `alger.prestige.cars@demo.dz` | Approuvée, de confiance |
| Agence Gold | `oran.auto.location@demo.dz` | Approuvée |
| Agence Gold | `constantine.rent@demo.dz` | Approuvée |
| Agence Silver | `setif.drive@demo.dz` | Approuvée |
| Agence Silver | `annaba.wheels@demo.dz` | Approuvée |
| Agence | `bejaia.car.services@demo.dz` | **En attente** |
| Agence | `tlemcen.location.express@demo.dz` | **Rejetée** |

Les deux dernières existent pour que les écrans de modération aient quelque
chose à montrer sur une installation neuve.

### Ce qui a été vérifié

**20 tests Pest** sur la phase, dont : le registre de commerce n'atterrit jamais
sur le disque public, une commune d'une autre wilaya est refusée, une validation
qui échoue ne laisse ni utilisateur ni agence orphelins, une agence ne peut pas
lire le registre d'une concurrente, chaque décision est inscrite au journal
d'audit, une agence suspendue garde l'accès en lecture.

**Parcours navigateur complet** : inscription réelle avec upload, redirection
vers l'écran d'attente, tentative de forcer le tableau de bord, back office
invisible, connexion administrateur, rejet sans motif refusé, rejet avec motif,
approbation, fiche agence. Zéro erreur JavaScript.

---

## Phase 3 — Annonces

### Ce qui existe

**Mes véhicules** — `/agence/vehicules`. Grille filtrable par statut et par
marque, avec la jauge de quota en permanence à l'écran. Les annonces refusées
puis les brouillons remontent en premier : ce sont elles qui attendent une
action de l'agence.

**Formulaire d'annonce** — `/agence/vehicules/nouveau` puis `.../modifier`, en
quatre étapes : le véhicule, le retrait et les options, les tarifs, les photos.
Une étape qui porte une erreur de validation se colore en rouge dans la barre
d'étapes, sinon l'agence chercherait dans le formulaire ce que le serveur a
déjà pointé du doigt.

**Photos** — envoi multiple, choix de la couverture, suppression. Trois dérivés
WebP sont écrits à l'envoi (1600 / 800 / 240 px) : la page de recherche affiche
des dizaines de cartes à la fois, et une connexion algérienne n'est pas
l'endroit où envoyer la photo brute d'un téléphone.

**File de modération** — `/admin/annonces`. Les plus anciennes d'abord, filtres
par statut, wilaya et agence, sélection multiple pour le traitement par lot.

**Prévisualisation** — `/admin/annonces/{id}` montre la galerie, les
caractéristiques et les tarifs comme les verra le visiteur. Approuver sur un
tableau récapitulatif, c'est approuver sans avoir regardé.

### Structure du code

```
app/Services/
  ListingQuotaService        les quotas de formule posés en questions,
                             plus la phrase de refus à afficher
  VehicleService             création, édition, soumission, archivage
  VehiclePhotoService        dérivés WebP, couverture, ordre, nettoyage
  VehicleModerationService   approuver / rejeter / par lot + audit + emails

app/Http/Requests/Agency/VehicleRequest   création et édition partagent
                                          les mêmes règles
app/Policies/VehiclePolicy                cloisonnement entre agences
app/Notifications/                        VehicleApproved, VehicleRejected
```

### Décisions prises en construisant

**Une annonce occupe une place de quota sauf si elle est archivée.** Une
annonce refusée compte donc : c'est un travail que l'agence est censée corriger
et resoumettre. L'archivage est la sortie réversible, et il est toujours
disponible.

**Toute création part en brouillon.** Une agence remplit un formulaire en
plusieurs fois ; une annonce à moitié écrite n'a rien à faire dans la file de
modération.

**Deux conditions pour soumettre** : au moins une photo, au moins un tarif
journalier. Elles sont vérifiées au moment de soumettre et non dans le
formulaire, parce qu'une édition peut avoir supprimé la dernière photo.

**Modifier une annonce publiée la repasse en modération, sauf agence de
confiance** — mais elle reste visible pendant l'attente. Retirer du site une
annonce qui marche parce que son numéro de téléphone a changé punirait l'agence
d'avoir tenu sa page à jour.

**Un lot de photos est refusé en entier** s'il dépasse le quota. En accepter la
première et jeter les trois autres serait pire qu'un refus franc.

**Le message de quota est calculé côté serveur**, et il nomme la formule qui
lève réellement la limite. Proposer une formule qui accorde la même chose
serait un argument commercial, pas une information.

**Le tri « rejeté d'abord » est écrit en `CASE`, pas en `FIELD()`.** MySQL a
`FIELD()`, SQLite — la base des tests — ne l'a pas.

**Intervention Image 4.2** a renommé `read()`/`create()` en
`decodeSplFileInfo()`/`createImage()`, et l'encodage passe par
`encode(new WebpEncoder(...))`. Les exemples en ligne de la v4.0 ne compilent
plus.

### Données de démonstration

`VehicleSeeder` crée 24 annonces réparties sur les cinq agences approuvées :
12 publiées, 6 en attente de modération, 6 refusées avec motif. Les photos sont
des aplats de couleur générés — contrairement à un faux registre de commerce,
un rectangle uni ne peut pas être pris pour une photo de voiture, et sans image
la prévisualisation afficherait chaque annonce comme impubliable.

### Ce qui a été vérifié

**30 tests Pest** sur la phase, dont : le quota d'annonces bloque et
l'archivage libère une place, la formule illimitée ne bute sur rien, un tarif
hebdomadaire sans remise est refusé, une commune d'une autre wilaya est
refusée, une annonce sans photo ne se soumet pas, une agence de confiance
publie sans passer par la file, une agence ne touche pas aux annonces d'une
concurrente, une agence suspendue lit sans écrire, un lot de photos hors quota
est refusé entièrement, les trois dérivés sont écrits et effacés avec
l'annonce, la couverture se transmet à la photo suivante, un rejet sans motif
est refusé, un lot n'approuve que les annonces réellement en attente, chaque
décision est inscrite au journal d'audit.

**Parcours HTTP complet** sur les trois rôles : back office invisible aux
agences, prévisualisation admin, édition d'une annonce d'une concurrente
refusée en 403, quota Platinium sans plafond.

---

## Phase 4 — Site public (en cours)

### Ce qui existe

**Accueil** — `/`. Barre de recherche wilaya/commune, véhicules mis en avant
(Platinium uniquement), dernières annonces, catégories et wilayas cliquables
avec leurs compteurs réels. Si rien n'est publié, la page le dit au lieu
d'afficher une grille vide.

**Résultats** — `/location-voiture`, `/location-voiture/{wilaya}` et
`/location-voiture/{wilaya}/{commune}`. Filtres latéraux (catégorie, boîte,
carburant, places, prix maximum, climatisation, chauffeur), tri manuel,
pagination, fil d'Ariane.

**Fiche véhicule** — `/vehicule/{id}-{slug}`. Galerie, caractéristiques,
tarifs dégressifs avec leur équivalent journalier, conditions de location,
encart agence avec téléphone et lien WhatsApp, véhicules similaires.

### Décisions prises en construisant

**Le lieu vit dans le chemin, les filtres dans la requête.** Une wilaya et une
commune décrivent une page qu'un moteur de recherche doit indexer ; un filtre
carburant décrit un état passager de cette page.

**Le tri de la §4.2 est écrit une seule fois**, dans `PublicListingService` :
priorité wilaya Platinium, puis priorité commune Gold et Platinium, puis le
reste. Un classement commercial recopié dans trois contrôleurs finirait par
diverger dans l'un des trois.

**La rotation équitable est de l'arithmétique, pas `RAND(seed)`.** Elle doit
tourner sur SQLite comme sur MySQL, et surtout rester identique d'une page de
résultats à la suivante : un tirage réellement aléatoire ferait apparaître une
annonce deux fois et une autre jamais.

**La mention « Sponsorisé » est calculée côté serveur**, dans la carte, et non
dans la page Vue. Une annonce remontée par la formule de son agence doit le
dire quel que soit l'écran qui l'affiche.

**Le tri manuel écrase les priorités de formule** — c'est le choix de
transparence assumé au cahier des charges : qui demande le moins cher obtient
le moins cher.

**Les colonnes de formule passent par des sous-requêtes, pas par une
jointure** : une agence portant deux lignes dans `subscriptions` dupliquerait
sinon chacune de ses annonces dans les résultats.

**Le prix se filtre par `EXISTS` sur la règle journalière**, pas par `HAVING`
sur l'alias : SQLite refuse un `HAVING` sans `GROUP BY`, et c'est la base des
tests.

### Ce qui a été vérifié

**12 tests Pest**, dont : ni brouillon ni annonce en modération ou refusée
n'atteint le public, les annonces d'une agence suspendue disparaissent du
catalogue, la fiche d'une annonce non publiée répond 404 même à qui détient
l'URL, une commune étrangère à la wilaya de l'URL répond 404, le Platinium
passe devant sur sa wilaya avec la mention « Sponsorisé », le tri manuel ignore
cette priorité, et seul le Platinium occupe l'emplacement d'accueil.

### Complété ensuite

**Fiche agence publique** — `/agence-de-location/{slug}` : présentation,
horaires, conditions, carte, et tous ses véhicules en ligne. Invisible pour une
agence suspendue, en attente ou rejetée : ses annonces sont masquées, une page
qui les annoncerait encore contredirait la suspension.

**Paramètres de l'agence** — `/agence/parametres`. C'était un manque réel :
les conditions de location, la caution et l'âge minimum s'affichaient déjà au
client dans le tunnel de réservation et sur le bon PDF, mais **seul un seeder
pouvait les remplir**. L'écran couvre aussi le logo, la présentation, les
horaires des sept jours et la position sur la carte. Le registre de commerce et
le NIF y sont affichés mais non modifiables : ils ont servi à valider l'agence,
les changer sans nouvelle vérification viderait la modération de son sens.

**Carte Leaflet** sur fond OpenStreetMap, sans clé d'API (§7.1). Elle
n'apparaît sur la fiche publique **que si l'agence a posé son point** : centrer
sur le chef-lieu de la wilaya afficherait une adresse qui n'est pas la sienne.
Le composant est chargé à part par Vite — 44 Ko compressés que ne paient que
les deux pages qui l'utilisent.

**Données structurées Schema.org** — `Car` avec son offre sur la fiche
véhicule, `AutoRental` avec `PostalAddress` et `GeoCoordinates` sur la fiche
agence. `aggregateRating` n'est émis que s'il existe des avis : une note
agrégée sans avis est une étoile inventée, que Google sanctionne et que le
client découvre en cliquant.

**Sitemap XML** — `/sitemap.xml`, reconstruit chaque heure. Il ne liste que des
pages qui répondent : une wilaya sans une seule annonce publiée en est exclue,
parce qu'un sitemap qui pointe vers des pages vides apprend au robot à se
méfier du fichier entier.

**Cinq pages statiques** — à propos, CGU, confidentialité, FAQ, contact. Leur
texte vit dans `PageController` et non dans les gabarits Vue : c'est de la
prose juridique et éditoriale qu'un juriste peut avoir à amender, et la
chercher dans un template n'est pas la bonne façon.

### Ce qui reste sur la phase 4

**La carte des résultats de recherche** (§7.1, « liste + carte »). Elle est
désormais possible : les agences peuvent poser leurs coordonnées. Reste à
décider si un véhicule s'affiche à l'adresse de son agence ou à son propre
point de retrait, qui peut en différer.

**Le calendrier de disponibilité en grille** sur la fiche véhicule. Les dates
prises sont bien calculées et affichées, mais sous forme de liste dans le
tunnel de réservation, pas encore en grille mensuelle.

**L'i18n arabe** (§7.2) : les noms arabes des 69 wilayas et 1541 communes sont
en base, l'interface reste en français. Structure à monter en phase 8.

---

## Phase 5 — Réservations

### Ce qui existe

**Tunnel public** — `/vehicule/{id}/reserver`, en trois étapes : dates et
options, coordonnées, récapitulatif. Ouvert aux visiteurs : le compte client
est créé au passage et la session ouverte, sans mot de passe à choisir.

**Devis en direct** — `/vehicule/{id}/devis` renvoie le détail du calcul et la
disponibilité. Le total vient du serveur : les règles tarifaires sont
combinées par un service dont le navigateur n'a pas de copie, et un prix
calculé en JavaScript risquerait de ne pas être celui qui sera facturé.

**Espace client** — `/mes-reservations` : à venir et historique séparés,
annulation avec motif, bon de réservation PDF, appel et WhatsApp vers l'agence.

**Espace agence** — `/agence/reservations` (file triée par urgence, filtres,
export CSV), fiche détaillée avec les coordonnées du client, et les quatre
boutons du cycle : Accepter, Refuser, Départ effectué, Retour effectué.

**Calendrier** — `/agence/calendrier` : tous les véhicules sur une grille
mensuelle, réservations et blocages manuels, création et levée d'un blocage.

**Expiration automatique** — `bookings:expire`, planifiée toutes les heures.

### Décisions prises en construisant

**Une demande en attente gèle déjà les dates.** Deux clients ne doivent pas
pouvoir faire la queue sur le même créneau en s'entendant dire oui tous les
deux.

**Mais une demande en attente ne bloque pas une confirmation.** C'est la
distinction qui a demandé le plus de soin : si les `pending` bloquaient à la
confirmation, une agence ne pourrait jamais accepter dès que deux clients
visent les mêmes dates. `AvailabilityService::COMMITTED_STATUSES` — confirmée
et en cours — est ce que la confirmation vérifie ; `BLOCKING_STATUSES` — les
mêmes plus `pending` — est ce que la disponibilité publique vérifie.

**La revérification à la confirmation se fait sous `lockForUpdate`** (§6.3).
C'est ce qui sépare une plateforme d'un fichier Excel partagé : deux
confirmations simultanées, la seconde attend la première et lit ce qu'elle a
écrit.

**Le prix est le plus avantageux, pas le plus littéral.** Le calcul est une
programmation dynamique sur les règles publiées : une semaine à 28 000 est
appliquée même sur six jours si six jours coûteraient 30 000. Une agence qui
publie un tarif hebdomadaire s'engage dessus ; faire chercher au client la
combinaison la moins chère serait malhonnête.

**Le prix est gelé à la demande.** Une agence qui augmente ses tarifs le
lendemain ne change pas le prix d'une demande déjà déposée : `price_breakdown`
conserve le détail affiché au client.

**Le tampon inter-locations se compte en jours entiers.** Quatre heures de
nettoyage coûtent une journée au calendrier : la voiture ne se remet pas deux
fois le même jour.

**Un blocage manuel ne peut pas recouvrir une réservation vivante.** L'agence
doit d'abord annuler, avec un motif que le client reçoit — sinon la plateforme
masquerait une location déjà promise.

**Le motif est obligatoire dans les deux sens.** Un client qui annule sans un
mot est une agence qui planifie à l'aveugle.

**Le bon PDF est généré à la demande, jamais stocké** : il ne contient rien qui
ne soit dans la réservation, et un fichier périmé contredirait une annulation.
Il utilise **Helvetica et non DejaVu Sans** : la police embarquée pesait 850 Ko
par bon téléchargé, contre 2,8 Ko sans. Corollaire à connaître — les polices de
base de dompdf n'ont pas l'apostrophe typographique `’`, qui disparaît
silencieusement : le gabarit utilise l'apostrophe droite.

### Ce qui a été vérifié

**42 tests Pest** sur la phase. Côté moteur : chevauchement en intervalle fermé
dans les trois sens, statuts qui rendent leurs dates, tampon, blocages manuels,
tarif dégressif choisi contre le tarif journalier, prix gelé, cycle complet,
transitions absurdes refusées, **double confirmation concurrente refusée**,
expiration à 24 h, numérotation annuelle. Côté HTTP : tunnel ouvert aux
visiteurs, annonce non publiée non réservable, conditions à accepter,
cloisonnement entre agences, client qui ne peut pas s'auto-accepter, agence
suspendue en lecture seule, blocage refusé sur des dates réservées, bon PDF
réservé aux réservations confirmées et à leurs deux parties.

**Parcours HTTP complet** : demande déposée par un visiteur non connecté,
acceptée par l'agence, bon PDF téléchargé — accents et apostrophes vérifiés
dans le texte extrait du PDF.

### Ce qui reste

**L'invitation à laisser un avis 24 h après le retour** (§6.2) : le champ
`review_invited_at` existe, le job appartient à la phase 7 avec les avis.

**Le glisser-déposer sur le calendrier** (§8) : les blocages se créent
aujourd'hui par un formulaire, ce qui fait le même travail en trois clics.

---

## Phase 6 — Formules

### Ce qui existe

**Écran admin des formules** — `/admin/formules` : la matrice du §4.1 se
modifie ici (quotas, priorités, badge, statistiques, prix), avec le nombre
d'agences sur chaque formule, la file des demandes de changement et les
échéances à trente jours.

**Attribution manuelle** — l'administrateur attribue une formule avec une
échéance et une note visible par l'agence, une fois le règlement encaissé hors
plateforme (§4.3). Deux chemins :

- depuis **la fiche agence** (`/admin/agences/{id}`), bouton « Attribuer une
  formule » — sans qu'aucune demande n'ait été faite ;
- depuis **la file des demandes** (`/admin/formules`), où accepter une demande
  l'attribue et la clôt du même geste.

Le premier manquait : l'attribution n'était accessible que depuis une demande
de l'agence, si bien qu'un administrateur ne pouvait rien attribuer de sa
propre initiative — alors que c'est lui qui constate l'encaissement.

**Mon abonnement** — `/agence/abonnement` : formule en cours, échéance,
tableau comparatif et bouton « Demander ». Une seule demande à la fois.

**Commande planifiée** — `subscriptions:refresh`, chaque jour à 6 h : prévient
à sept jours, rétrograde après échéance.

### Décisions prises en construisant

**La matrice est lue en base partout, jusque dans les emails.** L'écran admin
est ce qui rend vraie la promesse du §4.1 — « jamais codées en dur ». J'avais
écrit `$silverMax = 5` dans la notification d'échéance : c'est exactement ce
que la spécification interdit, et l'email aurait menti dès la première
modification de la matrice.

**Baisser un quota agit immédiatement.** Ramener Gold de 20 à 5 annonces
archive sur-le-champ ce qui dépasse chez les agences concernées, qui reçoivent
la liste. Laisser l'écart se résorber « à la prochaine rétrogradation »
reviendrait à laisser publier au-delà de ce qui est payé.

**Rien n'est supprimé, jamais** (§4.3). Les annonces excédentaires passent en
`archived` : photos, tarifs et historique intacts. L'agence republie celles de
son choix en archivant les autres. L'email les **nomme une par une** — « certaines
de vos annonces » obligerait à deviner, et deviner mal fait republier la
mauvaise voiture.

**Les plus anciennes partent d'abord.** Le travail le plus récent de l'agence
est celui auquel elle tient ; tirer au sort serait pire que choisir mal.

**Une échéance déjà passée est refusée à l'attribution** : elle rétrograderait
l'agence dès la nuit suivante, ce qui n'est jamais ce que l'administrateur veut
dire.

**`expiry_notified_at` rend l'avertissement idempotent** : la commande tourne
tous les jours, l'agence est prévenue une fois.

**Une agence rétrogradée garde toujours un abonnement actif.** La formule
Silver est *attribuée*, pas seulement constatée : chaque contrôle de quota pose
une question et doit obtenir une réponse.

### Un défaut corrigé au passage

**`activeSubscription()` pouvait renvoyer « aucune formule ».** La relation
s'écrivait `->where('status', 'active')->latestOfMany('starts_at')` : Laravel
construit l'agrégat *sans* la contrainte, retient la ligne la plus récente tous
statuts confondus, puis filtre. Une agence dont la dernière ligne était annulée
tombait donc silencieusement sur Silver. Corrigé en passant la contrainte à
l'intérieur de l'agrégat, via `ofMany()`. Le cas ne se produisait pas avec les
données réelles — `grant()` crée toujours la nouvelle ligne à la date du jour —
mais il attendait la première reprise d'un abonnement rétroactif.

### Ce qui a été vérifié

**19 tests Pest**, dont : rétrogradation d'une formule expirée, formule sans
échéance laissée tranquille, archivage des plus anciennes d'abord, photos et
tarifs conservés, email nommant les annonces perdues, aucun email quand rien
n'est archivé, formule illimitée épargnée, avertissement à sept jours envoyé
une seule fois, matrice modifiable, baisse de quota appliquée sur-le-champ,
`max_listings` à NULL accepté, une seule demande à la fois, échéance passée
refusée, motif obligatoire au refus, agence suspendue en lecture seule.

**Parcours HTTP complet** : demande déposée par une agence Silver, Gold
attribuée par l'administrateur avec échéance et note, échéance forcée dans le
passé, `subscriptions:refresh` rétrograde en Silver ; puis quota Silver ramené
à 2 depuis l'écran admin — six annonces archivées chez trois agences, journal
d'audit à l'appui, quota et annonces restaurés ensuite.

### Ce qui reste

**Les comptes multi-utilisateurs** (`max_users`, table `agency_users`) : le
quota est dans la matrice et la table existe, mais aucun écran ne permet
d'inviter un collaborateur.

---

## Phase 7 — Statistiques et avis

### Ce qui existe

**Compteurs journaliers** — une ligne par annonce et par jour dans
`listing_stats` : vues, clics de contact, demandes. Les vues sont comptées à
l'ouverture de la fiche, les contacts au clic sur le téléphone ou WhatsApp, les
demandes à la création de la réservation.

**Statistiques agence** — `/agence/statistiques`, en 7, 30 ou 90 jours :
chiffres clés, courbe d'évolution, jours de forte demande, véhicules les plus
vus, taux de conversion, comparaison avec la wilaya. Graphiques Chart.js.

**Avis** — dépôt par le client depuis « Mes réservations », modération admin
sur `/admin/avis`, consultation et réponse publique sur `/agence/avis`,
affichage sur la fiche agence publique.

**Invitation automatique** — `reviews:invite`, toutes les heures, 24 h après le
retour du véhicule.

**Données de démonstration** — `DemoActivitySeeder` : 30 jours de compteurs,
27 réservations réparties sur les statuts, 13 avis dont quelques-uns laissés en
modération.

### Décisions prises en construisant

**Les blocs verrouillés sont calculés quand même.** La page les floute et
propose la montée en gamme (§8) ; envoyer des chiffres vides derrière le flou
vendrait la fonction avec un mensonge. Ce qui est verrouillé, c'est l'accès,
pas le calcul.

**La courbe remplit les jours creux.** Un graphique qui saute les journées sans
vue flatterait l'agence en les cachant.

**La comparaison wilaya se fait par annonce, pas par agence.** Une agence de
quarante voitures paraîtrait toujours devant une agence de quatre, ce qui ne
dit rien de la performance de l'une ou de l'autre.

**Les compteurs s'incrémentent en SQL, pas en lecture-modification-écriture.**
Deux visiteurs sur la même annonce à la même seconde ne doivent pas se perdre
une vue ; l'index unique `(vehicle_id, date)` arbitre la course à la création
de la ligne.

**Un avis est attaché à une réservation, jamais à une agence.** Seul quelqu'un
qui a réellement loué peut noter, et l'index unique sur `booking_id` empêche de
gonfler une note par répétition.

**La note n'est comptée qu'après modération**, et un avis écarté après
publication cesse aussitôt de peser : `recomputeAgencyRating()` est appelé dans
les deux sens.

**La note est stockée sur l'agence, pas calculée à la lecture.** Elle s'affiche
sur chaque carte de chaque résultat de recherche, et une note qui coûte une
jointure par ligne est une note que personne n'affiche.

**L'auteur d'un avis public est réduit au prénom et à l'initiale.** Un nom
complet n'a rien à faire sur une page publique indexée.

**L'invitation attend 24 h.** Demandé au comptoir, un client répond par
politesse ; le lendemain, il répond ce qu'il pense. `review_invited_at` rend la
commande idempotente, et un client qui a déjà répondu n'est jamais relancé.

### Un écart corrigé au passage

**`stats_level` : le code disait `full`, la base dit `premium`.** Le
`PlanSeeder` de la phase 1 écrivait `premium` pour Platinium ; j'avais écrit
`full` partout dans le service, l'écran et la validation admin. Le niveau le
plus élevé n'aurait jamais été reconnu — la comparaison wilaya serait restée
verrouillée pour un Platinium, et l'écran d'administration aurait refusé
d'enregistrer la formule. Aligné sur la valeur en base plutôt que l'inverse :
changer la donnée aurait demandé une migration pour les lignes existantes.

### Ce qui a été vérifié

**29 tests Pest**, dont : une vue comptée par jour et par annonce, clic de
contact enregistré, rien compté pour une annonce dépubliée, jours creux
remplis, conversion calculée, classement des véhicules, niveau de statistiques
tiré de la formule, comparaison wilaya réservée à Platinium, blocs verrouillés
calculés quand même, cloisonnement entre agences, période limitée aux valeurs
prévues ; côté avis : dépôt refusé avant la fin de la location, un seul avis
par réservation, avis d'autrui interdit, note comptée seulement après
publication et retirée après un écart, motif obligatoire, réponse réservée aux
formules qui l'incluent, réponse à l'avis d'une concurrente interdite, seuls
les avis approuvés publiés, note agrégée Schema.org, invitation à 24 h
idempotente.

**Parcours HTTP complet** : location menée jusqu'au retour, avis déposé par le
client, publié par l'administrateur — note de l'agence passée de 0 à 5/5 —,
réponse refusée à une agence Silver, avis visible sur la fiche publique sous
« Nadir T. » avec la note agrégée dans les données structurées.

### Ce qui reste

**Les statistiques côté administrateur** (§9) : la vue globale de la
plateforme n'existe pas encore, le tableau de bord admin se limite aux files
d'attente.

**L'export des statistiques** promis à la formule Platinium (§4.1).

---

## Phase 8 — Finition (en cours)

### Ce qui existe

**Canaux de notification configurables** — `config/notifications.php` décide,
par notification, des canaux employés. Le §10 demande de pouvoir brancher plus
tard des SMS ou WhatsApp Business : le contrat `SmsGateway`, le canal Laravel
`SmsChannel` et une implémentation de développement qui écrit dans le log sont
en place. Activer le SMS pour les demandes de réservation est **une ligne de
configuration**, pas une reprise du code.

**Rappel J-1** — `bookings:remind`, chaque jour à 17 h, aux deux parties. Il
manquait au tableau du §10.

**Échéance d'abonnement à l'administrateur** — le §10 adresse cette alerte à
l'agence *et* à l'admin ; seule l'agence la recevait.

**i18n arabe** — `lang/fr.json` et `lang/ar.json`, bascule par
`POST /langue` gardée en session, `dir="rtl"` posé par le gabarit, composable
`useTranslations` côté Vue. L'accueil et la carte véhicule sont traduits comme
preuve de bout en bout.

**Cache des données de référence** (§12) — `ReferenceDataService` met en cache
les wilayas et la matrice des formules, vidé explicitement quand l'une ou
l'autre change.

**Budget de requêtes** — cinq tests comptent les requêtes des pages les plus
chargées et échouent si un `with()` disparaît.

### Décisions prises en construisant

**Un canal SMS actif sans passerelle est ignoré.** Il enverrait dans le vide
tout en faisant croire que le client a été prévenu — pire qu'un canal éteint.

**Une notification sans `toSms()` est simplement sautée**, jamais en erreur :
activer un canal ne doit pas casser les seize notifications existantes.

**Le SMS est réservé à l'urgent** : la demande à laquelle l'agence a 24 h pour
répondre, la confirmation que le client attend, le rappel de la veille. Un SMS
se facture au segment de 160 signes ; en envoyer pour un avis publié
gaspillerait de l'argent et de l'attention.

**Le rappel J-1 ne part que pour une réservation confirmée.** Rappeler une
location que l'agence n'a pas acceptée promettrait une voiture que personne ne
s'est engagé à remettre. `reminded_at` — colonne ajoutée par migration — évite
qu'une commande horaire envoie vingt-quatre fois le même SMS.

**La langue vit en session, pas dans l'URL.** Un visiteur qui bascule en arabe
garde tous les liens qu'il a déjà, et les URLs indexables du §7.2 restent
uniques : une page, une adresse, quelle que soit la langue de lecture. Un
préfixe `/ar` reste possible plus tard sans rien déplacer.

**La clé de traduction est la phrase française.** Une page pas encore traduite
reste lisible au lieu d'afficher `home.hero.title` — c'est ce qui permet à
l'arabe de n'être que partiellement rempli en v1, comme le §7.2 l'autorise.

**Le cache des références est vidé explicitement, pas laissé expirer.** Une
modification de la matrice invisible pendant une heure ferait refaire la
manipulation à l'administrateur, croyant qu'elle n'a pas pris.

### Ce qui a été vérifié

**24 tests Pest** sur la phase : canaux lus depuis la configuration, canal SMS
ignoré sans passerelle, envoi passé à la passerelle, notification sans texte
SMS sautée, rappel J-1 aux deux parties, rappel unique, pas de rappel sans
confirmation, admin prévenu de l'échéance, wilayas et matrice mises en cache,
cache vidé au changement de quota, cache cohérent avec la base ; langue par
défaut, bascule arabe retenue, `dir` écrit dans le document, langue inconnue
refusée, langue du navigateur suivie, URLs inchangées selon la langue,
dictionnaires alignés ; et cinq budgets de requêtes.

**Bascule vérifiée dans le navigateur réel** : `locale=ar`, `dir="rtl"`,
« Rechercher » rendu « بحث ».

### L'arabe couvre désormais tout le site public

Recherche, fiche véhicule, fiche agence, tunnel de réservation, confirmation et
pages statiques : **123 clés**, alignées entre `fr.json` et `ar.json`.

**Un libellé construit côté serveur suit la langue lui aussi.** Les horaires
d'ouverture renvoient « Fermé » depuis PHP : `__()` lit les mêmes fichiers que
le navigateur, il n'y a qu'un dictionnaire.

**Deux tests gardent la couverture.** Le premier relit les appels `t('…')` du
code Vue et échoue si une clé manque à l'arabe — une clé absente retombe
silencieusement en français au milieu d'une page arabe, ce qui est une
traduction *oubliée*, pas un choix. Le second refuse l'accumulation de
traductions que plus personne n'utilise.

### Un piège corrigé au passage

**Le cache des traductions ne s'invalidait jamais.** Il était posé en
`rememberForever` sur la seule locale : les 51 clés ajoutées ce jour-là étaient
bien dans le fichier, bien vues par les tests — qui lisent le disque — et
**absentes du navigateur**, qui lit le cache. Rien ne le signalait. La clé de
cache porte maintenant la date de modification du fichier, si bien qu'un
dictionnaire mis à jour s'invalide de lui-même. Un test dépose une clé sonde
dans `fr.json` et vérifie qu'elle arrive jusqu'à la page.

### Ce qui reste sur la phase 8

**Le SMTP réel** : la configuration est documentée au README, mais aucun compte
d'envoi n'a été fourni.

**Le worker de queue** doit tourner en production (`php artisan queue:work`),
sinon aucune notification ne part.

**Les espaces agence et administration restent en français.** C'est assumé pour
la v1 : leurs utilisateurs sont professionnels et le §7.2 ne vise que le site
public.

---

## Points ouverts pour la suite

**Emails** — les notifications existent et sont en file d'attente, mais
`MAIL_MAILER=log` : elles atterrissent dans `storage/logs`. Le passage en SMTP
et la mise en forme font partie de la phase 8.

**Le `role` sur `users` double les rôles spatie.** C'est assumé : la colonne
rend les requêtes courantes lisibles et bon marché, les rôles spatie restent la
source de vérité pour les permissions. Les deux sont écrits ensemble à
l'inscription.

**Impersonation** (se connecter en tant qu'agence pour le support) est demandée
au §9 mais n'est pas encore faite — elle a plus de sens quand il y aura des
réservations à observer.

**Le réordonnancement des photos a sa route et son service** mais pas encore de
glisser-déposer : l'ordre se règle aujourd'hui en choisissant la couverture.

**La rétrogradation de formule n'archive pas encore les annonces
excédentaires** (§4.3). Le point d'accroche existe — `ListingQuotaService` sait
compter, `VehicleService::archive()` sait archiver — mais la commande planifiée
appartient à la phase 6.

**Le seeder ne dépose aucun fichier de registre de commerce.** Un faux document
que l'administrateur pourrait ouvrir et prendre pour un vrai serait pire
qu'absent. La fiche affiche alors « Aucun registre de commerce n'a été fourni ».

---

## Commandes

```powershell
$env:PATH = "D:\wamp64\bin\php\php8.4.15;" + $env:PATH
cd "D:\wamp64\www\Autokrili"

php artisan migrate:fresh --seed    # base propre + démonstration
php artisan serve                   # http://localhost:8000
npm run dev                         # assets, dans un second terminal
php artisan test                    # suite Pest
```
