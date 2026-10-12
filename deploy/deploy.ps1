<#
  deploy.ps1 - Playdrive uitrollen naar de productieserver (Hetzner, 2.29.32.40)

  1. gewijzigde bestanden committen (bericht: versie uit .deploy/version + bestandsnamen) en pushen
     - nieuwe, nog niet gevolgde bestanden: lijst + vraag of ze mee moeten (standaard nee)
     - nieuwe bestanden in de codemappen (app, config, database/migrations|seeders|factories,
       resources, routes, lang, tests, deploy) gaan altijd mee
  2. de server haalt de nieuwste deploy/deploy-playdrive.sh uit GitHub en draait die
     (bouwen in een aparte map, checkout, composer, migraties met DB-backup, optimize, rooktest)
     Staat er nog geen code op de server, dan draait de eerste installatie (/root/playdrive/deploy.sh).

  Gebruik (vanuit C:\wamp64\www\playdrive), of via de npm-tab: deploy:server
    deploy\deploy.bat              uitrollen
    deploy\deploy.bat status       enkel de serverstatus tonen
    deploy\deploy.bat rollback     vorige versie terug (migraties blijven staan)
#>
param(
  [string]$Server = "root@2.29.32.40",
  [string]$Branch = "master",
  [switch]$Rollback,
  [switch]$Status
)
$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)
$script = "/root/playdrive-deploy/deploy-playdrive.sh"
# git als eigenaar van de code (playdrive), want die heeft de deploy key voor GitHub
$fetch  = "set -e; mkdir -p /root/playdrive-deploy; O=`$(stat -c %U /var/www/playdrive/artisan); " +
          "sudo -u `$O -H git -C /var/www/playdrive fetch -q --prune origin; " +
          "sudo -u `$O -H git -C /var/www/playdrive show origin/${Branch}:deploy/deploy-playdrive.sh > $script; "

if ($Status)   { & ssh $Server ($fetch + "bash $script --status");   exit $LASTEXITCODE }
if ($Rollback) { & ssh $Server ($fetch + "bash $script --rollback"); exit $LASTEXITCODE }

# --- 1. git -----------------------------------------------------------------------------
$ver = (Get-Content .deploy\version -ErrorAction SilentlyContinue | Select-Object -First 1)
# Nieuwe bestanden in de codemappen gaan altijd mee (Claude schrijft rechtstreeks in de
# projectmap); .gitignore blijft gelden. Al de rest: lijst + vraag hieronder.
$codeDirs = @('app', 'config', 'database/migrations', 'database/seeders', 'database/factories',
              'resources', 'routes', 'lang', 'tests', 'deploy') | Where-Object { Test-Path $_ }
git add -- $codeDirs
if ($LASTEXITCODE -ne 0) { throw "git add codemappen mislukt" }
$untracked = @(git ls-files --others --exclude-standard)
if ($untracked.Count -gt 0) {
  Write-Host "Nieuwe, nog niet gevolgde bestanden:" -ForegroundColor Yellow
  $untracked | Select-Object -First 30 | ForEach-Object { Write-Host "   $_" -ForegroundColor Yellow }
  if ($untracked.Count -gt 30) { Write-Host ("   (+" + ($untracked.Count - 30) + " meer)") -ForegroundColor Yellow }
  $a = Read-Host "Mee committen? (j/N)"
  if ($a -match '^[jJyY]') {
    git add -- $untracked
    if ($LASTEXITCODE -ne 0) { throw "git add mislukt" }
  } else { Write-Host "   -> gaan NIET mee" -ForegroundColor Yellow }
}
$changed = @(git diff --name-only HEAD)
if ($changed.Count -gt 0) {
  $files = $changed | Where-Object { $_ -notlike '.deploy/*' } | ForEach-Object { Split-Path $_ -Leaf } | Select-Object -Unique
  $list  = ($files | Select-Object -First 5) -join ", "
  if ($files.Count -gt 5) { $list += " (+" + ($files.Count - 5) + ")" }
  $msg = $(if ($ver) { "$ver" } else { "Deploy" }) + " (" + (Get-Date -Format "yyyy-MM-dd HH:mm") + "): " + $list
  Write-Host "Commit: $msg" -ForegroundColor Cyan
  git commit -q -a -m $msg
  if ($LASTEXITCODE -ne 0) { throw "git commit mislukt" }
}
git push origin $Branch
if ($LASTEXITCODE -ne 0) { throw "git push mislukt" }
Write-Host ("Gepusht: " + (git log -1 --format="%h  %s")) -ForegroundColor Green

# --- 2. server --------------------------------------------------------------------------
# eerste keer (nog geen code op de server): volledige installatie via /root/playdrive/deploy.sh
& ssh $Server "test -f /var/www/playdrive/artisan"
if ($LASTEXITCODE -ne 0) {
  Write-Host "Nog geen code op de server -> eerste installatie (/root/playdrive/deploy.sh)" -ForegroundColor Cyan
  & ssh $Server "bash /root/playdrive/deploy.sh"
  if ($LASTEXITCODE -ne 0) { throw "eerste installatie mislukt (zie uitvoer hierboven)" }
  exit 0
}
& ssh $Server ($fetch + "bash $script $Branch")
if ($LASTEXITCODE -ne 0) { throw "deploy op de server mislukt (zie uitvoer hierboven)" }
