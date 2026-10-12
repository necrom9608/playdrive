#!/usr/bin/env bash
# =============================================================================
#  deploy-playdrive.sh [branch]     Playdrive uitrollen op de Hetzner-server (standaard: master)
#  deploy-playdrive.sh --status     enkel kijken: git, versies, diensten, migraties (verandert niets)
#  deploy-playdrive.sh --key        deploy key tonen + verbinding met GitHub testen
#  deploy-playdrive.sh --setup      build-map aanmaken (gebeurt ook automatisch bij de eerste deploy)
#  deploy-playdrive.sh --rollback   vorige uitgerolde code terug (migraties blijven staan!)
#  deploy-playdrive.sh --smoke      enkel de rooktest
#
#  Eerste installatie (nog geen code in /var/www/playdrive): deploy.bat draait dan
#  /root/playdrive/deploy.sh (uit SCRIPTS\playdrive-hetzner) — .env, composer, build, migrate.
#
#  Werkwijze (zo kort mogelijk offline):
#   1. bouwen in een aparte map (/opt/playdrive-build, git worktree): npm ci (enkel als
#      package-lock wijzigde) + npm run build — mislukt dat, dan blijft productie onaangeroerd
#   2. pas dan in /var/www/playdrive: git checkout, composer (enkel als composer.lock wijzigde),
#      public/build omwisselen, DB-backup + migrate (enkel bij nieuwe migraties),
#      artisan optimize, queue:restart, reverb herstarten, opcache leegmaken, rooktest
#  Onderhoudsmodus (artisan down) enkel bij nieuwe migraties of composer-wijzigingen.
#  Seeders draaien NIET automatisch: zie "## Seeders" bij de versie in .deploy/changelog.md.
#  Staat in de repo (deploy/); op de pc start deploy\deploy.bat dit script.
# =============================================================================
set -Eeuo pipefail
APP=${PLAYDRIVE_APP:-/var/www/playdrive}
BUILD=${PLAYDRIVE_BUILD:-/opt/playdrive-build}
REPO=${PLAYDRIVE_REPO:-git@github.com:necrom9608/playdrive.git}
BRANCH_DEFAULT=master
STATE=${PLAYDRIVE_STATE:-/root/playdrive-deploy}
BACKUPS=/var/backups/playdrive-deploy
KEY_NAME=id_ed25519   # aangemaakt door setup.sh voor de gebruiker playdrive
CUR_FILE=$STATE/commit
PREV_FILE=$STATE/commit-prev
export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1
# git, composer, npm en artisan draaien als de eigenaar van de code (playdrive), zodat
# bestanden en caches dezelfde eigenaar houden als bij een handmatige deploy
OWNER=$(stat -c %U "$APP/artisan" 2>/dev/null || echo root)
OWNER_HOME=$(getent passwd "$OWNER" | cut -d: -f6)
as_owner() { if [[ "$OWNER" == root || "$(id -un)" == "$OWNER" ]]; then "$@"; else sudo -u "$OWNER" -H -- "$@"; fi; }
G()    { as_owner git "$@"; }
die()  { echo "FOUT: $*"; exit 1; }
art()  { (cd "$APP" && as_owner php artisan "$@"); }
envv() { grep -m1 "^$1=" "$APP/.env" 2>/dev/null | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/; s/^'"'"'(.*)'"'"'$/\1/' || true; }
webuser() { local u; u=$(stat -c %U "$APP/storage" 2>/dev/null || echo www-data); [[ "$u" == root ]] && u=www-data; echo "$u"; }
# groep waaronder php-fpm draait (www-data): storage/ en bootstrap/cache/ moeten voor die groep schrijfbaar zijn
fpm_group() { local g; g=$(grep -h '^group' /etc/php/*/fpm/pool.d/*.conf 2>/dev/null | awk '{print $3}' | head -1); echo "${g:-www-data}"; }
fix_perms() {   # zoals de vroegere deploy.sh: eigenaar:www-data, 775
  if [[ "$OWNER" == root ]]; then
    chown -R "$(webuser):$(webuser)" "$APP/storage" "$APP/bootstrap/cache"
  else
    chown -R "$OWNER:$(fpm_group)" "$APP/storage" "$APP/bootstrap/cache"
    chmod -R 775 "$APP/storage" "$APP/bootstrap/cache"
  fi
}
safe_dirs() {
  for d in "$APP" "$BUILD"; do
    git config --global --get-all safe.directory 2>/dev/null | grep -qx "$d" || git config --global --add safe.directory "$d"
  done
}

# --- status (alleen lezen) -------------------------------------------------------------
status() {
  echo "=== Playdrive-server: status $(date -Is) ==="
  echo "--- code ($APP)"
  safe_dirs
  echo "   origin : $(G -C "$APP" remote get-url origin 2>/dev/null || echo '?')"
  echo "   HEAD   : $(G -C "$APP" log -1 --format='%h  %ci  %s' 2>/dev/null || echo '?')"
  echo "   branch : $(G -C "$APP" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '?')"
  echo "   versie : $(cat "$APP/.deploy/version" 2>/dev/null || echo '?')   (.env PATCH_VERSION=$(envv PATCH_VERSION))"
  local dirty; dirty=$(G -C "$APP" status --porcelain --untracked-files=no 2>/dev/null || true)
  if [[ -n "$dirty" ]]; then
    echo "   !! gewijzigde bestanden op de server (gaan verloren bij een checkout):"
    echo "$dirty" | head -n 20 | sed 's/^/      /'
  else
    echo "   werkboom: schoon"
  fi
  echo "   untracked (eerste 10): $(G -C "$APP" status --porcelain 2>/dev/null | grep -c '^??' || true)"
  G -C "$APP" status --porcelain 2>/dev/null | grep '^??' | head -n 10 | sed 's/^/      /' || true
  echo "--- omgeving"
  echo "   APP_ENV=$(envv APP_ENV)  APP_DEBUG=$(envv APP_DEBUG)  APP_URL=$(envv APP_URL)"
  echo "   php $(php -r 'echo PHP_VERSION;')  |  node $(node -v 2>/dev/null || echo '-')  |  npm $(npm -v 2>/dev/null || echo '-')  |  $(composer -V 2>/dev/null | head -1 || echo 'composer -')"
  echo "   eigenaar code: $OWNER   |   eigenaar storage: $(stat -c %U "$APP/storage" 2>/dev/null || echo '?')"
  echo "   php-fpm draait als: $(grep -h '^user' /etc/php/*/fpm/pool.d/*.conf 2>/dev/null | awk '{print $3}' | sort -u | tr '\n' ' ') (groep $(fpm_group))"
  echo "--- diensten"
  for s in apache2 nginx php8.3-fpm php8.4-fpm mysql supervisor; do
    systemctl is-active --quiet "$s" 2>/dev/null && echo "   $s: actief"
  done
  systemctl list-units --type=service --all --no-legend 2>/dev/null | grep -Ei 'playdrive|reverb|queue|horizon|worker' | sed 's/^/   systemd: /' || true
  command -v supervisorctl >/dev/null && supervisorctl status 2>/dev/null | sed 's/^/   supervisor: /' || true
  echo "   processen:"
  pgrep -af 'artisan (queue:work|queue:listen|reverb:start|horizon)' | sed 's/^/      /' || echo "      (geen queue/reverb-processen gevonden)"
  echo "   cron (schedule:run):"
  { cat /var/spool/cron/crontabs/* /etc/crontab /etc/cron.d/* 2>/dev/null; command -v supervisorctl >/dev/null && cat /etc/supervisor/conf.d/*.conf 2>/dev/null; } | grep -iE 'schedule:(run|work)' | sed 's/^/      /' || echo "      !! geen schedule:run/schedule:work gevonden — geplande taken draaien niet"
  if G -C "$APP" fetch -q origin 2>/dev/null; then
    local ab; ab=$(G -C "$APP" rev-list --left-right --count "HEAD...origin/$BRANCH_DEFAULT" 2>/dev/null || echo "? ?")
    echo "   t.o.v. GitHub origin/$BRANCH_DEFAULT: ${ab%%[[:space:]]*} commit(s) enkel op de server, ${ab##*[[:space:]]} nog niet uitgerold"
  else
    echo "   (fetch van GitHub lukt nog niet met de huidige origin)"
  fi
  echo "--- migraties"
  local pend; pend=$(cd "$APP" && php artisan migrate:status 2>/dev/null | grep -ci pending || true)
  echo "   nog niet uitgevoerd: ${pend:-?}"
  echo "--- schijf"; df -h "$APP" | sed 's/^/   /'
}

# --- rooktest ---------------------------------------------------------------------------
smoke() {
  echo; echo "--- Rooktest ---"
  local url host code
  url=$(envv APP_URL); host=${url#*://}; host=${host%%/*}
  for p in /up /; do
    code=000
    if [[ "$url" == https://* ]]; then
      code=$(curl -s -o /dev/null -w '%{http_code}' --resolve "$host:443:127.0.0.1" "https://$host$p" || true)
    fi
    if [[ "$code" == 000 ]]; then   # (nog) geen TLS: via http
      code=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $host" "http://127.0.0.1$p" || true)
      echo "   GET http://$host$p -> $code"
    else
      echo "   GET $url$p -> $code"
    fi
  done
  echo "   versie: $(cat "$APP/.deploy/version" 2>/dev/null || echo '?')  |  build: $( [[ -f "$APP/public/build/manifest.json" ]] && echo ok || echo '!! manifest ontbreekt')"
  local pend; pend=$(cd "$APP" && php artisan migrate:status 2>/dev/null | grep -ci pending || true)
  [[ "$pend" == 0 ]] && echo "   migraties: alles uitgevoerd" || echo "   !! migraties nog niet uitgevoerd: $pend"
  # na queue:restart starten de workers pas opnieuw als supervisor ze terug opstart
  sleep 5
  echo "   queue-workers: $(pgrep -fc 'artisan queue:(work|listen)' || true)   reverb: $(pgrep -fc 'artisan reverb:start' || true)"
  if command -v supervisorctl >/dev/null; then
    supervisorctl status 2>/dev/null | grep -Ei 'worker|queue|reverb' | sed 's/^/   supervisor: /' || true
  fi
  echo "   recente fouten in laravel.log:"
  local lf; lf=$(ls -t "$APP"/storage/logs/laravel*.log 2>/dev/null | head -1 || true)
  if [[ -n "$lf" ]]; then grep -E '^\[[0-9-]+ [0-9:]+\] [a-z]+\.(ERROR|CRITICAL|ALERT|EMERGENCY)' "$lf" | tail -n 3 | cut -c1-200 | sed 's/^/     /' || true; fi
}

# --- reverb / queue / opcache -------------------------------------------------------------
restart_services() {
  art queue:restart >/dev/null 2>&1 && echo "   queue:restart verstuurd (workers herstarten na hun huidige job)" || true
  # reverb enkel herstarten als een procesbeheerder hem terug opstart
  local unit; unit=$(systemctl list-units --type=service --no-legend --plain 2>/dev/null | grep -Ei 'reverb' | awk '{print $1}' | head -1 || true)
  if [[ -n "$unit" ]]; then systemctl restart "$unit" && echo "   $unit herstart"
  elif command -v supervisorctl >/dev/null && supervisorctl status 2>/dev/null | grep -qi reverb; then
    supervisorctl restart "$(supervisorctl status | grep -i reverb | awk '{print $1}' | head -1)" >/dev/null && echo "   reverb (supervisor) herstart"
  elif pgrep -f 'artisan reverb:start' >/dev/null; then
    echo "   !! reverb draait zonder procesbeheerder — niet herstart (draai zelf 'php artisan reverb:restart' als reverb-code wijzigde)"
  fi
  for s in php8.3-fpm php8.4-fpm; do systemctl is-active --quiet "$s" 2>/dev/null && systemctl reload "$s" && echo "   $s herladen (opcache)"; done
  systemctl is-active --quiet apache2 2>/dev/null && systemctl reload apache2 && echo "   apache2 herladen"
  systemctl is-active --quiet nginx 2>/dev/null && systemctl reload nginx || true
}

# --- build-map (git worktree naast productie) -----------------------------------------------
ensure_build() {
  if [[ ! -e "$BUILD/.git" ]]; then
    install -d -o "$OWNER" -g "$(id -gn "$OWNER")" "$BUILD"
    G -C "$APP" worktree prune
    G -C "$APP" worktree add -q --force --detach "$BUILD" HEAD
    echo "build-map aangemaakt: $BUILD (eigenaar $OWNER)"
  fi
  as_owner ln -sfn "$APP/.env" "$BUILD/.env"
  [[ -s "$CUR_FILE" ]] || G -C "$APP" rev-parse HEAD > "$CUR_FILE"
}

# --- uitrollen ---------------------------------------------------------------------------
deploy() {
  local target=$1 hash cur rev newmig lockchg pkgchg downmode=0
  safe_dirs
  [[ -d "$BUILD/.git" || -f "$BUILD/.git" ]] || ensure_build
  # wijzigingen die rechtstreeks op de server gebeurden, nooit stil overschrijven
  # (bestandsrechten tellen niet mee: storage/ en bootstrap/cache/ krijgen bewust 775)
  G -C "$APP" config core.fileMode false
  local dirty; dirty=$(G -C "$APP" status --porcelain --untracked-files=no)
  [[ -z "$dirty" ]] || { echo "$dirty" | sed 's/^/   /'; die "er zijn gewijzigde bestanden op de server — eerst in git zetten of terugdraaien"; }

  G -C "$APP" fetch -q --prune --tags origin
  hash=$(G -C "$APP" rev-parse -q --verify "origin/$target^{commit}" 2>/dev/null \
      || G -C "$APP" rev-parse -q --verify "$target^{commit}" 2>/dev/null) || die "onbekende versie: $target"
  cur=$(G -C "$APP" rev-parse HEAD)
  rev=$(G -C "$APP" log -1 --format='%h  %ci  %s' "$hash")
  echo "huidig : $(G -C "$APP" log -1 --format='%h  %s' "$cur")  ($(cat "$APP/.deploy/version" 2>/dev/null || echo '?'))"
  echo "nieuw  : $rev  ($(G -C "$APP" show "$hash:.deploy/version" 2>/dev/null || echo '?'))"
  [[ "$hash" == "$cur" ]] && echo "(deze versie staat al live — opnieuw uitrollen)"
  if ! G -C "$APP" branch -r --contains "$cur" 2>/dev/null | grep -q . && [[ "${FORCE:-0}" != 1 ]]; then
    die "de huidige servercode ($cur) staat niet op GitHub (commits enkel op de server?) — eerst nakijken, of FORCE=1"
  fi
  newmig=$(G -C "$APP" diff --name-only --diff-filter=A "$cur" "$hash" -- database/migrations | wc -l)
  # ook migraties die vroeger al binnenkwamen maar nooit gedraaid zijn
  local pendnow; pendnow=$(art migrate:status 2>/dev/null | grep -ci pending || true)
  newmig=$(( newmig + ${pendnow:-0} ))
  lockchg=$(G -C "$APP" diff --quiet "$cur" "$hash" -- composer.lock && echo 0 || echo 1)
  echo "nieuwe migraties: $newmig  |  composer.lock gewijzigd: $([[ $lockchg == 1 ]] && echo ja || echo nee)"

  # 1. syntaxcontrole + build in de aparte map — productie blijft intussen draaien
  echo; echo "--- 1. Bouwen in $BUILD ---"
  G -C "$BUILD" checkout -q --force --detach "$hash"
  G -C "$BUILD" clean -qfd -e node_modules -e .env
  local bad files
  files=$(G -C "$BUILD" diff --name-only --diff-filter=AM "$cur" "$hash" -- '*.php' | grep -v '\.blade\.php$' || true)
  if [[ -n "$files" ]]; then
    bad=$(cd "$BUILD" && echo "$files" | xargs -P4 -I{} sh -c 'php -l "{}" 2>&1 || true' | grep -v '^No syntax errors' | grep -v '^$' || true)
    [[ -z "$bad" ]] || { echo "$bad" | sed 's/^/   !! /'; die "syntaxfouten — NIET uitgerold"; }
  fi
  echo "   php -l: ok ($(echo -n "$files" | grep -c . || true) gewijzigde bestanden)"
  # PATCH_VERSION in .env gelijkzetten met .deploy/version (zoals de watcher lokaal doet) — komt in de build
  local pv; pv=$(cat "$BUILD/.deploy/version" 2>/dev/null || true)
  if [[ -n "$pv" ]]; then
    if grep -q '^PATCH_VERSION=' "$APP/.env"; then as_owner sed -i "s/^PATCH_VERSION=.*/PATCH_VERSION=$pv/" "$APP/.env"; else echo "PATCH_VERSION=$pv" | as_owner tee -a "$APP/.env" >/dev/null; fi
  fi
  pkgchg=1
  [[ -d "$BUILD/node_modules" && "$(md5sum < "$BUILD/package-lock.json")" == "$(cat "$STATE/package-lock.md5" 2>/dev/null)" ]] && pkgchg=0
  if [[ $pkgchg == 1 ]]; then
    echo "   npm ci"
    (cd "$BUILD" && as_owner npm ci --no-audit --no-fund 2>&1 | tail -n 3 | sed 's/^/   /')
    md5sum < "$BUILD/package-lock.json" > "$STATE/package-lock.md5"
  fi
  echo "   npm run build"
  as_owner rm -rf "$BUILD/public/build"
  (cd "$BUILD" && as_owner env NODE_OPTIONS=--max-old-space-size=2048 npm run build 2>&1 | grep -Ev '^\s*$' | tail -n 4 | sed 's/^/   /')
  [[ -f "$BUILD/public/build/manifest.json" ]] || die "build mislukt (geen manifest) — NIET uitgerold"

  # 2. productie bijwerken
  echo; echo "--- 2. Uitrollen in $APP ---"
  if [[ $newmig -gt 0 ]]; then
    mkdir -p "$BACKUPS"
    local db f cnf; db=$(envv DB_DATABASE); f="$BACKUPS/$db-$(date +%Y%m%d-%H%M%S)-voor-${hash:0:7}.sql.gz"
    cnf=$(mktemp); chmod 600 "$cnf"
    printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\n' "$(h=$(envv DB_HOST); echo "${h:-127.0.0.1}")" "$(p=$(envv DB_PORT); echo "${p:-3306}")" "$(envv DB_USERNAME)" "$(envv DB_PASSWORD)" > "$cnf"
    echo "   DB-backup -> $f"
    if ! mysqldump --defaults-extra-file="$cnf" --single-transaction --quick --routines --triggers --no-tablespaces "$db" | gzip -1 > "$f"; then
      rm -f "$cnf" "$f"; die "DB-backup mislukt — NIET uitgerold"
    fi
    rm -f "$cnf"
    ls -1t "$BACKUPS"/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
  fi
  if [[ $newmig -gt 0 || $lockchg == 1 ]]; then
    downmode=1
    art down --retry=15 >/dev/null 2>&1 || true
    trap 'art up >/dev/null 2>&1 || true' EXIT
    echo "   onderhoudsmodus aan"
  fi
  G -C "$APP" checkout -q --force --detach "$hash"
  if [[ $lockchg == 1 || ! -f "$APP/vendor/autoload.php" ]]; then
    echo "   composer install"
    (cd "$APP" && as_owner composer install --no-dev --optimize-autoloader --no-progress 2>&1 | tail -n 2 | sed 's/^/   /')
  fi
  # public/build omwisselen
  as_owner rm -rf "$APP/public/build.new" "$APP/public/build.old"
  as_owner cp -a "$BUILD/public/build" "$APP/public/build.new"
  [[ -d "$APP/public/build" ]] && as_owner mv "$APP/public/build" "$APP/public/build.old"
  as_owner mv "$APP/public/build.new" "$APP/public/build"
  as_owner rm -rf "$APP/public/build.old"
  echo "   public/build vervangen"
  if [[ $newmig -gt 0 ]]; then
    echo "   migrate"
    local mout mrc=0
    mout=$(art migrate --force 2>&1) || mrc=$?
    echo "$mout" | grep -Ev '^\s*$' | sed 's/^/   /'
    if [[ $mrc -ne 0 ]]; then
      trap - EXIT
      echo "!! migratie mislukt — de site BLIJFT in onderhoud."
      echo "   Herstel: DB-backup terugzetten ($BACKUPS) en '$0 --rollback', of de fout oplossen en opnieuw uitrollen."
      echo "   Site toch openzetten: cd $APP && php artisan up"
      exit 1
    fi
  fi
  fix_perms
  art optimize:clear >/dev/null
  local oout
  if oout=$(art optimize 2>&1); then
    echo "$oout" | grep -E 'DONE' | sed 's/^/   /' || true
  else
    echo "$oout" | tail -n 5 | sed 's/^/   !! /'
    art optimize:clear >/dev/null 2>&1 || true
    echo "   !! artisan optimize mislukt — site draait zonder cache"
  fi
  fix_perms
  # ook een onderhoudsmodus van een eerder mislukte poging opheffen
  if [[ $downmode == 1 || -f "$APP/storage/framework/down" ]]; then art up >/dev/null; trap - EXIT; echo "   onderhoudsmodus uit"; fi
  restart_services

  [[ "$cur" != "$hash" ]] && echo "$cur" > "$PREV_FILE"
  echo "$hash" > "$CUR_FILE"
  echo; echo "uitgerold: $rev  ($(cat "$APP/.deploy/version" 2>/dev/null || echo '?'))"
  if G -C "$APP" diff --name-only "$cur" "$hash" -- database/seeders | grep -q .; then
    echo "LET OP: seeders gewijzigd — draai de seeder(s) uit .deploy/changelog.md zelf, bv.:"
    G -C "$APP" diff --name-only "$cur" "$hash" -- database/seeders | sed 's#database/seeders/##; s#\.php$##; s#^#   php artisan db:seed --force --class=#'
  fi
}

case "${1:-}" in
  --status) status; exit 0 ;;
  --smoke)  smoke; exit 0 ;;
  --key)
    SSHD=$OWNER_HOME/.ssh; KEY=$SSHD/$KEY_NAME
    as_owner install -d -m 700 "$SSHD"
    [[ -f "$KEY" ]] || as_owner ssh-keygen -q -t ed25519 -N '' -C "playdrive@hetzner" -f "$KEY"
    as_owner ssh-keygen -F github.com >/dev/null 2>&1 || ssh-keyscan -t ed25519,ecdsa,rsa github.com 2>/dev/null | as_owner tee -a "$SSHD/known_hosts" >/dev/null
    echo "GitHub > playdrive > Settings > Deploy keys > Add deploy key (Allow write access UIT):"
    echo; cat "$KEY.pub"; echo
    echo "Verbindingstest (verwacht: 'Hi necrom9608/playdrive!'):"
    as_owner ssh -o BatchMode=yes -T git@github.com 2>&1 | head -1 || true
    exit 0 ;;
esac

mkdir -p "$STATE"
LOG=$STATE/deploy-$(date +%Y%m%d-%H%M%S).log
exec > >(tee -a "$LOG") 2>&1
exec 9>/run/lock/deploy-playdrive.lock
flock -n 9 || die "er loopt al een deploy-playdrive"
echo "=== deploy-playdrive.sh $(date -Is) ${*:-} ==="

case "${1:-}" in
  --setup)
    [[ -d "$APP/.git" ]] || die "$APP is geen git-clone"
    safe_dirs
    G -C "$APP" config core.fileMode false
    old=$(G -C "$APP" remote get-url origin)
    if G -C "$APP" fetch -q --prune origin 2>/dev/null; then
      echo "fetch als $OWNER met de huidige origin ($old): ok — origin blijft"
    else
      [[ -f "$OWNER_HOME/.ssh/$KEY_NAME" ]] || die "fetch als $OWNER lukt niet — eerst: $0 --key (en de sleutel op GitHub zetten)"
      echo "$old" > "$STATE/origin-vroeger"
      G -C "$APP" remote set-url origin "$REPO"
      echo "origin: $old -> $REPO  (oude waarde in $STATE/origin-vroeger)"
      G -C "$APP" fetch -q --prune origin || { G -C "$APP" remote set-url origin "$old"; die "fetch via de deploy key mislukt — origin teruggezet"; }
      echo "fetch via deploy key: ok"
    fi
    ensure_build
    echo "huidige versie vastgelegd: $(G -C "$APP" log -1 --format='%h  %s')"
    status ;;
  --rollback)
    # na een mislukte poging staat de code al op de nieuwe versie: dan terug naar de laatst geslaagde
    C=$(cat "$CUR_FILE" 2>/dev/null || true)
    if [[ -n "$C" && "$(G -C "$APP" rev-parse HEAD)" != "$C" ]]; then
      T=$C; echo "vorige poging niet afgerond — terug naar de laatst geslaagde versie"
    else
      T=$(cat "$PREV_FILE" 2>/dev/null || true)
    fi
    [[ -n "$T" ]] || die "geen vorige versie bekend ($PREV_FILE)"
    echo "LET OP: migraties worden niet teruggedraaid (backup in $BACKUPS)"
    deploy "$T"; smoke ;;
  *)
    deploy "${1:-$BRANCH_DEFAULT}"; smoke ;;
esac
echo; echo "=== klaar $(date -Is) — log: $LOG ==="
