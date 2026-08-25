#!/usr/bin/env bash
#
# Mise en ligne d'Autokrili sur un mutualise Hostinger.
#
# A lancer depuis le dossier de l'application :
#   cd ~/domains/<domaine>/autokrili_app && bash deploy/mise-en-ligne.sh
#
# Le script est rejouable : le relancer apres correction d'une erreur
# reprend la ou il s'etait arrete, sans rien casser de ce qui est deja fait.
#
# Il ne se termine jamais par « exit » sur une erreur previsible : il
# affiche ce qui manque et rend la main. Colle dans un shell interactif,
# un « exit » deconnecterait la session.

set -u

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOM="$(dirname "$APP")"
WEB="$DOM/public_html"

rouge()  { printf '\033[31m%s\033[0m\n' "$*"; }
vert()   { printf '\033[32m%s\033[0m\n' "$*"; }
etape()  { printf '\n\033[1m%s\033[0m\n' "$*"; }

echec() { rouge "ARRET : $*"; return 1; }

env_get() {
    # Lit une cle du .env sans l'exporter : les valeurs peuvent contenir
    # des caracteres que le shell interpreterait.
    sed -n "s/^$1=//p" "$APP/.env" | head -1 | sed 's/^"//; s/"$//'
}

deployer() {
    etape "[1/5] Verifications"

    [ -d "$APP/vendor" ] || {
        echec "vendor/ absent. Lancez :"
        echo "  cd $APP && composer install --no-dev --optimize-autoloader"
        return 1
    }
    [ -f "$APP/.env" ] || {
        echec ".env absent. Il n'est jamais dans git : creez-le a la main."
        return 1
    }
    grep -q '^APP_KEY=base64:' "$APP/.env" || {
        echec "APP_KEY vide. Lancez : cd $APP && php artisan key:generate"
        return 1
    }
    grep -q 'XXXX' "$APP/.env" && {
        echec "Le .env contient encore des XXXX : identifiants MySQL non remplis."
        return 1
    }
    [ -d "$WEB" ] || { echec "Racine web introuvable : $WEB"; return 1; }

    # L'erreur MySQL est affichee telle quelle : « connexion refusee » sans
    # le message du serveur ne dit pas si c'est la base, l'utilisateur ou
    # le mot de passe qui est en cause.
    local db user
    db="$(env_get DB_DATABASE)"
    user="$(env_get DB_USERNAME)"
    if ! MYSQL_PWD="$(env_get DB_PASSWORD)" mysql -u "$user" "$db" -e 'SELECT 1' >/dev/null 2>/tmp/mysql-err.txt; then
        echec "MySQL refuse la connexion."
        echo "  base        : $db"
        echo "  utilisateur : $user"
        echo "  message     : $(cat /tmp/mysql-err.txt)"
        echo
        echo "  Chez Hostinger, le nom reel porte toujours le prefixe du compte"
        echo "  (u516979428_...), et l'utilisateur doit etre rattache a la base"
        echo "  dans hPanel : ce sont deux operations distinctes."
        return 1
    fi
    vert "  vendor, .env, APP_KEY et MySQL : OK"

    etape "[2/5] Racine web"
    # Seuls les fichiers a la racine sont deplaces, pas les sous-dossiers,
    # et ils sont mis de cote plutot que supprimes.
    mkdir -p "$DOM/_sauvegarde_page_defaut"
    find "$WEB" -maxdepth 1 -type f ! -name '.htaccess' \
        -exec mv -t "$DOM/_sauvegarde_page_defaut" {} + 2>/dev/null || true
    cp -r "$APP/public/." "$WEB/"

    # Sans ces trois chemins, PHP cherche vendor/ dans la racine web, ne le
    # trouve pas, et le site repond 500 sans rien expliquer.
    sed -i "s#__DIR__\.'/\.\./#__DIR__.'/../autokrili_app/#g" "$WEB/index.php"
    grep -q "autokrili_app/vendor" "$WEB/index.php" || {
        echec "index.php n'a pas ete corrige : chemins introuvables."
        return 1
    }
    # Vestige du serveur de developpement : present, toutes les pages iraient
    # chercher leurs assets sur localhost:5173 et le site serait blanc.
    rm -f "$WEB/hot"
    vert "  contenu de public/ en place, index.php corrige"

    etape "[3/5] Photos"
    mkdir -p "$WEB/storage"
    cp -r "$APP/storage/app/public/vehicles" "$WEB/storage/" 2>/dev/null || true
    vert "  fichiers servis : $(find "$WEB/storage" -type f 2>/dev/null | wc -l)"

    etape "[4/5] Base de donnees"
    local tables
    tables="$(MYSQL_PWD="$(env_get DB_PASSWORD)" mysql -u "$user" "$db" -N -e \
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()' 2>/dev/null)"
    if [ "${tables:-0}" -gt 0 ]; then
        vert "  $tables tables deja presentes, import ignore"
    else
        MYSQL_PWD="$(env_get DB_PASSWORD)" mysql -u "$user" "$db" < "$APP/deploy/demo-data.sql" || {
            echec "L'import du dump a echoue."
            return 1
        }
        vert "  dump importe"
    fi

    etape "[5/5] Droits et caches"
    chmod -R 775 "$APP/storage" "$APP/bootstrap/cache" 2>/dev/null || true
    (cd "$APP" && php artisan config:clear >/dev/null && php artisan view:clear >/dev/null)
    vert "  caches vides"

    etape "Resultat"
    local url code
    url="$(env_get APP_URL)"
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "$url/")"
    if [ "$code" = "200" ]; then
        vert "  $url repond HTTP 200 — le site est en ligne."
    else
        rouge "  $url repond HTTP $code"
        echo "  Dernieres lignes du journal :"
        tail -n 15 "$APP/storage/logs/laravel.log" 2>/dev/null | sed 's/^/    /'
    fi
}

deployer
