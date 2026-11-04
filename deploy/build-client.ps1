<#
  build-client.ps1   Playdrive Desktop (kassa-app, Tauri) bouwen en klaarzetten voor automatische updates

  Gestart door Playmix Ops (project PlayDrive -> "Client bouwen..."), of met de hand:
    powershell -ExecutionPolicy Bypass -File deploy\build-client.ps1 [-Release]

  -Release   versie ophogen en klaarzetten in public\desktop\ (installer + latest.json).
             Daarna: "Commit, push & deploy" zet ze online; de kassa's werken zichzelf bij
             (desktop\src-tauri\src\commands\updater.rs).
  zonder     enkel bouwen (test), niets klaarzetten.

  Omgeving (Playmix Ops zet die):
    MAVISYS_RELEASE_NOTES          nieuwigheden (komen in latest.json)
    MAVISYS_CERT_PASS              wachtwoord van de updater-sleutel (leeg = geen wachtwoord)
  Optioneel:
    PLAYDRIVE_UPDATER_KEY          pad naar de privésleutel (standaard %USERPROFILE%\.tauri\playdrive-desktop.key)
    PLAYDRIVE_UPDATER_KEY_PASSWORD wachtwoord, als MAVISYS_CERT_PASS leeg is

  Eerste keer: de updater-sleutel wordt aangemaakt en de publieke sleutel komt in tauri.conf.json.
  BEWAAR DE PRIVESLEUTEL GOED (bv. in je wachtwoordbeheer): zonder kan je geen updates meer
  uitrollen en moet elke kassa opnieuw met de hand geïnstalleerd worden.

  Vereist: Node + npm, Rust (cargo), en de Tauri-vereisten voor Windows (WebView2, MSVC Build Tools).
#>
param(
    [switch]$Release,
    [switch]$Verplicht   # (Mavisys) — hier zonder betekenis
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$BaseUrl = 'https://playdrive.be/desktop'
$Root = Split-Path -Parent $PSScriptRoot
$Desktop = Join-Path $Root 'desktop'
$ConfPath = Join-Path $Desktop 'src-tauri\tauri.conf.json'
$OutDir = Join-Path $Root 'public\desktop'
$LatestPath = Join-Path $OutDir 'latest.json'
$BundleDir = Join-Path $Desktop 'src-tauri\target\release\bundle\nsis'
$Utf8 = New-Object System.Text.UTF8Encoding $false

function Step([string]$name) {
    Write-Output ('::ops::step ' + (@{ name = $name } | ConvertTo-Json -Compress))
}
function Fail([string]$msg) {
    Write-Output "FOUT: $msg"
    exit 1
}
function Run([string]$what, [scriptblock]$cmd) {
    & $cmd
    if ($LASTEXITCODE -ne 0) { Fail "$what mislukt (code $LASTEXITCODE)" }
}
function ReadText([string]$path) { [System.IO.File]::ReadAllText($path, $Utf8) }
function WriteText([string]$path, [string]$text) { [System.IO.File]::WriteAllText($path, $text, $Utf8) }

function NextVersion([string]$current, [string]$released) {
    # Altijd hoger dan wat klaarstaat: anders zien de kassa's geen update.
    if (-not $released) { return $current }
    $c = [version]$current
    $r = [version]$released
    if ($c -gt $r) { return $current }
    return ('{0}.{1}.{2}' -f $r.Major, $r.Minor, ($r.Build + 1))
}

# ---------------------------------------------------------------------------------------------
Step 'Controle'
if (-not (Test-Path $ConfPath)) { Fail "$ConfPath niet gevonden" }
foreach ($tool in 'node', 'npm', 'cargo') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { Fail "$tool niet gevonden (PATH)" }
}

$conf = ReadText $ConfPath
$m = [regex]::Match($conf, '"version"\s*:\s*"(\d+\.\d+\.\d+)"')
if (-not $m.Success) { Fail 'geen "version" in tauri.conf.json' }
$current = $m.Groups[1].Value

$released = $null
if (Test-Path $LatestPath) {
    try { $released = (ConvertFrom-Json (ReadText $LatestPath)).version } catch { $released = $null }
}

$version = if ($Release) { NextVersion $current $released } else { $current }
Write-Output "   versie in de code: $current  |  klaargezet: $(if ($released) { $released } else { '-' })  |  deze build: $version"

if ($version -ne $current) {
    $conf = [regex]::Replace($conf, '("version"\s*:\s*")\d+\.\d+\.\d+(")', "`${1}$version`${2}", 1)
    WriteText $ConfPath $conf
    Write-Output "   tauri.conf.json -> $version"
}

# ---------------------------------------------------------------------------------------------
Step 'Updater-sleutel'
$KeyPath = if ($env:PLAYDRIVE_UPDATER_KEY) { $env:PLAYDRIVE_UPDATER_KEY } else { Join-Path $env:USERPROFILE '.tauri\playdrive-desktop.key' }
$KeyPass = if ($env:MAVISYS_CERT_PASS) { $env:MAVISYS_CERT_PASS } elseif ($env:PLAYDRIVE_UPDATER_KEY_PASSWORD) { $env:PLAYDRIVE_UPDATER_KEY_PASSWORD } else { '' }

Push-Location $Desktop
try {
    # npm ci als node_modules ontbreekt of package-lock.json wijzigde (anders botsen de versies
    # van @tauri-apps/* met de Rust-crate tauri)
    $lockHash = (Get-FileHash (Join-Path $Desktop 'package-lock.json') -Algorithm SHA256).Hash
    $lockMark = Join-Path $Desktop 'node_modules\.playdrive-lock'
    $installed = if (Test-Path $lockMark) { (ReadText $lockMark).Trim() } else { '' }
    if ($installed -ne $lockHash) {
        Step 'npm-pakketten'
        Run 'npm ci' { npm ci --no-audit --no-fund }
        WriteText $lockMark $lockHash
    }

    if (-not (Test-Path $KeyPath)) {
        $pubInConf = [regex]::Match($conf, '"pubkey"\s*:\s*"([^"]*)"').Groups[1].Value
        if ($pubInConf) {
            Fail "privésleutel $KeyPath ontbreekt, maar tauri.conf.json heeft al een publieke sleutel. Zet de bewaarde sleutel terug (of PLAYDRIVE_UPDATER_KEY)."
        }
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $KeyPath) | Out-Null
        Write-Output "   nieuwe updater-sleutel: $KeyPath"
        if ($KeyPass) {
            Run 'sleutel aanmaken' { npx tauri signer generate --ci -w $KeyPath -p $KeyPass }
        } else {
            Run 'sleutel aanmaken' { npx tauri signer generate --ci -w $KeyPath }
        }
        Write-Output '   !! Bewaar deze sleutel goed (wachtwoordbeheer). Zonder kan je geen updates meer uitrollen.'
    }

    $pub = (ReadText "$KeyPath.pub").Trim()
    if (-not $pub) { Fail "$KeyPath.pub is leeg" }
    $pubInConf = [regex]::Match($conf, '"pubkey"\s*:\s*"([^"]*)"').Groups[1].Value
    if (-not $pubInConf) {
        $conf = [regex]::Replace($conf, '("pubkey"\s*:\s*")[^"]*(")', "`${1}$pub`${2}", 1)
        WriteText $ConfPath $conf
        Write-Output '   publieke sleutel in tauri.conf.json gezet'
    } elseif ($pubInConf -ne $pub) {
        Fail 'de publieke sleutel in tauri.conf.json hoort niet bij deze privésleutel — de kassa''s zouden de update weigeren'
    } else {
        Write-Output '   ok'
    }

    # -----------------------------------------------------------------------------------------
    Step "Bouwen (tauri build, $version)"
    # Inhoud van de sleutel (werkt in alle Tauri-versies; het pad kan via TAURI_SIGNING_PRIVATE_KEY_PATH)
    $env:TAURI_SIGNING_PRIVATE_KEY = (ReadText $KeyPath).Trim()
    $env:TAURI_SIGNING_PRIVATE_KEY_PASSWORD = $KeyPass
    if (Test-Path $BundleDir) { Remove-Item -Recurse -Force $BundleDir }
    Run 'tauri build' { npx tauri build --bundles nsis }
} finally {
    Remove-Item Env:TAURI_SIGNING_PRIVATE_KEY -ErrorAction SilentlyContinue
    Remove-Item Env:TAURI_SIGNING_PRIVATE_KEY_PASSWORD -ErrorAction SilentlyContinue
    Pop-Location
}

$exe = Get-ChildItem -Path $BundleDir -Filter "*_${version}_*-setup.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
if (-not $exe) { Fail "installer voor $version niet gevonden in $BundleDir" }
$sigPath = "$($exe.FullName).sig"
if (-not (Test-Path $sigPath)) { Fail "handtekening $sigPath ontbreekt (createUpdaterArtifacts / sleutel?)" }
Write-Output "   $($exe.Name)  ($([math]::Round($exe.Length / 1MB, 1)) MB)"

if (-not $Release) {
    Write-Output ''
    Write-Output "== Klaar (test): $($exe.FullName) — niets klaargezet."
    exit 0
}

# ---------------------------------------------------------------------------------------------
Step 'Klaarzetten in public\desktop'
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
# Enkel de nieuwste installer bewaren (oudere versies blijven in de git-historiek).
Get-ChildItem -Path $OutDir -Filter 'Playdrive-Desktop-*-setup.exe' -ErrorAction SilentlyContinue | Remove-Item -Force
$fileName = "Playdrive-Desktop-$version-x64-setup.exe"
Copy-Item -Force $exe.FullName (Join-Path $OutDir $fileName)

$notes = if ($env:MAVISYS_RELEASE_NOTES) { $env:MAVISYS_RELEASE_NOTES } else { "Playdrive Desktop $version" }
$latest = [ordered]@{
    version  = $version
    notes    = $notes
    pub_date = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
    platforms = [ordered]@{
        'windows-x86_64' = [ordered]@{
            signature = (ReadText $sigPath).Trim()
            url       = "$BaseUrl/$fileName"
        }
    }
}
WriteText $LatestPath ($latest | ConvertTo-Json -Depth 5)
Write-Output "   public\desktop\$fileName"
Write-Output '   public\desktop\latest.json'

Write-Output ''
Write-Output "== Klaar: Playdrive Desktop $version staat klaar. Nu: Commit, push & deploy — daarna werken de kassa's zichzelf bij."
