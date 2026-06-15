# ============================================================
#  git-helpers.ps1  -  Git + .deploy/ + .env helpers
#  Single-project versie (in-project)
# ============================================================

function Test-GitRepo($path) {
    if (-not (Test-Path $path)) { return $false }
    return (Test-Path (Join-Path $path ".git"))
}

function Get-GitConfig {
    # Defaults voor in-project deploy. Branch is configureerbaar via .env (DEPLOY_GIT_BRANCH).
    $branch = "master"
    $envBranch = Get-EnvValue "DEPLOY_GIT_BRANCH"
    if (-not [string]::IsNullOrWhiteSpace($envBranch)) { $branch = $envBranch }

    return @{
        enabled              = $true
        branch               = $branch
        autoPullOnStart      = (Get-EnvBool "DEPLOY_GIT_AUTOPULL_ON_START" $true)
        autoPullBeforeDeploy = (Get-EnvBool "DEPLOY_GIT_AUTOPULL_BEFORE_DEPLOY" $true)
        autoCommit           = (Get-EnvBool "DEPLOY_GIT_AUTOCOMMIT" $true)
    }
}

function Get-GitStatus($projectRoot) {
    if (-not (Test-GitRepo $projectRoot)) { return $null }

    Push-Location $projectRoot
    try {
        $branch = (git rev-parse --abbrev-ref HEAD 2>$null)
        if ($null -ne $branch) { $branch = $branch.Trim() }

        $statusOutput = git status --porcelain 2>$null
        $uncommitted = if ($null -eq $statusOutput) { 0 } else { ($statusOutput | Measure-Object).Count }

        git fetch 2>$null | Out-Null

        $ahead = 0
        $behind = 0
        $hasUpstream = $false
        $upstream = git rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>$null
        if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace($upstream)) {
            $hasUpstream = $true
            $countsLine = git rev-list --left-right --count "HEAD...$($upstream.Trim())" 2>$null
            if ($null -ne $countsLine) {
                $counts = $countsLine.Trim() -split '\s+'
                if ($counts.Count -ge 2) {
                    $ahead = [int]$counts[0]
                    $behind = [int]$counts[1]
                }
            }
        }

        return @{
            branch       = $branch
            uncommitted  = $uncommitted
            ahead        = $ahead
            behind       = $behind
            hasUpstream  = $hasUpstream
        }
    } finally {
        Pop-Location
    }
}

function Invoke-GitPull($projectRoot, $branch) {
    Push-Location $projectRoot
    try {
        $output = git pull origin $branch 2>&1
        $success = $LASTEXITCODE -eq 0

        $hasUpdates = $false
        $outStr = ($output | Out-String)
        if ($outStr -notmatch 'Already up.to.date') {
            $hasUpdates = $true
        }

        return @{
            success    = $success
            output     = $outStr.Trim()
            hasUpdates = $hasUpdates
        }
    } finally {
        Pop-Location
    }
}

function Invoke-GitCommit($projectRoot, $message) {
    Push-Location $projectRoot
    try {
        $statusOutput = git status --porcelain 2>$null
        if ([string]::IsNullOrWhiteSpace(($statusOutput | Out-String))) {
            return @{ success = $true; nothing = $true }
        }

        git add . 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0) {
            return @{ success = $false; message = "git add gefaald" }
        }

        $output = git commit -m $message 2>&1
        if ($LASTEXITCODE -ne 0) {
            return @{ success = $false; message = ($output | Out-String).Trim() }
        }

        $hash = (git rev-parse --short HEAD 2>$null)
        if ($null -ne $hash) { $hash = $hash.Trim() }

        return @{ success = $true; hash = $hash; nothing = $false }
    } finally {
        Pop-Location
    }
}

function Format-CommitMessage($version, $updateInfoRaw) {
    $firstLine = ""
    if (-not [string]::IsNullOrWhiteSpace($updateInfoRaw)) {
        $lines = $updateInfoRaw -split "`n"
        $inWijzigingen = $false
        foreach ($line in $lines) {
            $trimmed = $line.Trim()
            if ($trimmed -match '^##\s*Wijzigingen') { $inWijzigingen = $true; continue }
            if ($trimmed -match '^##\s' -and $inWijzigingen) { break }
            if (-not $inWijzigingen) { continue }
            if ([string]::IsNullOrWhiteSpace($trimmed)) { continue }

            $firstLine = $trimmed -replace '^[-*]\s+', ''
            if ($firstLine.Length -gt 60) {
                $firstLine = $firstLine.Substring(0, 57) + "..."
            }
            break
        }
    }

    $vLabel = "V$($version.ToString().PadLeft(3,'0'))"
    if ([string]::IsNullOrWhiteSpace($firstLine)) {
        return $vLabel
    }
    return "$vLabel - $firstLine"
}

# ============================================================
#  .env lezen/schrijven
# ============================================================
#
# .env is de lokale config per machine. We lezen er de PATCH_VERSION
# uit voor de "current version" weergave en schrijven hem na een
# succesvolle deploy weer terug zodat .env altijd in sync staat met
# .deploy/version (die de waarheid is, gecommit in git).

function Get-EnvPath {
    return Join-Path $script:ProjectRoot ".env"
}

function Get-EnvValue($key) {
    $envPath = Get-EnvPath
    if (-not (Test-Path $envPath)) { return $null }

    $content = Get-Content $envPath -Raw -ErrorAction SilentlyContinue
    if ([string]::IsNullOrWhiteSpace($content)) { return $null }

    $pattern = "(?m)^$([regex]::Escape($key))=(.*)$"
    if ($content -match $pattern) {
        $val = $Matches[1].Trim()
        # Strip surrounding quotes
        if ($val -match '^"(.*)"$') { $val = $Matches[1] }
        if ($val -match "^'(.*)'$") { $val = $Matches[1] }
        return $val
    }
    return $null
}

function Get-EnvBool($key, $default) {
    $val = Get-EnvValue $key
    if ($null -eq $val) { return $default }
    $lower = $val.ToString().Trim().ToLower()
    if ($lower -in @("true", "1", "yes", "on")) { return $true }
    if ($lower -in @("false", "0", "no", "off")) { return $false }
    return $default
}

function Set-EnvValue($key, $value) {
    $envPath = Get-EnvPath
    if (-not (Test-Path $envPath)) {
        # Geen .env aanwezig — niets te doen, deze waarde is alleen een spiegel
        return $false
    }

    $content = Get-Content $envPath -Raw -ErrorAction SilentlyContinue
    if ($null -eq $content) { $content = "" }

    $newLine = "$key=$value"
    $pattern = "(?m)^$([regex]::Escape($key))=.*$"

    if ($content -match $pattern) {
        $newContent = [regex]::Replace($content, $pattern, $newLine)
    } else {
        # Toevoegen op een nette plek: na APP_VERSION als die bestaat, anders bovenaan
        if ($content -match "(?m)^APP_VERSION=.*$") {
            $newContent = [regex]::Replace($content, "(?m)^(APP_VERSION=.*)$", "`$1`r`n$newLine")
        } else {
            # Append met nette newline
            $sep = if ($content.EndsWith("`n")) { "" } else { "`r`n" }
            $newContent = "$content$sep$newLine`r`n"
        }
    }

    # Schrijf zonder BOM, behoud line-endings zoals ze waren
    [System.IO.File]::WriteAllText($envPath, $newContent, [System.Text.UTF8Encoding]::new($false))
    return $true
}

function Get-AppVersion($projectRoot) {
    # Backwards compat: oude code roept Get-AppVersion met expliciet pad
    $script:ProjectRoot = $projectRoot
    $val = Get-EnvValue "APP_VERSION"
    if ($val) { return $val }
    return Get-EnvValue "VITE_APP_VERSION"
}

# ============================================================
#  .deploy/ map management
#  .deploy/version is de WAARHEID, gecommit in git.
#  .env PATCH_VERSION is een spiegel.
# ============================================================

function Get-DeployMetaPath($projectRoot) {
    return Join-Path $projectRoot ".deploy"
}

function Initialize-DeployMeta($projectRoot) {
    $metaPath = Get-DeployMetaPath $projectRoot
    if (-not (Test-Path $metaPath)) {
        New-Item -ItemType Directory -Path $metaPath -Force | Out-Null
    }

    $readme = Join-Path $metaPath "README.md"
    if (-not (Test-Path $readme)) {
        @"
# .deploy/

Deze map bevat metadata van het deploy systeem en wordt automatisch onderhouden.
Niet handmatig wijzigen.

- ``version`` - huidige patch versie (bijv. V324) - WAARHEID, gecommit in git
- ``changelog.md`` - historiek van patches met omschrijving
- ``context.txt`` - prompt voor nieuwe Claude chat sessies
- ``overview.md`` - vast projectoverzicht, wordt in context.txt geinjecteerd

De .env van elke machine heeft ``PATCH_VERSION`` als spiegel van ``version``.
Bij een nieuwe machine: na ``git pull`` is .deploy/version meteen correct,
en de watcher zet .env bij eerste start vanzelf gelijk.
"@ | Set-Content $readme
    }
}

function Get-DeployVersion($projectRoot) {
    $versionFile = Join-Path (Get-DeployMetaPath $projectRoot) "version"
    if (-not (Test-Path $versionFile)) { return 0 }

    $content = (Get-Content $versionFile -Raw -ErrorAction SilentlyContinue)
    if ([string]::IsNullOrWhiteSpace($content)) { return 0 }

    if ($content.Trim() -match '^V?(\d+)$') {
        return [int]$Matches[1]
    }
    return 0
}

function Format-VersionLabel($version) {
    return "V$($version.ToString().PadLeft(3,'0'))"
}

function Set-DeployVersion($projectRoot, $version) {
    Initialize-DeployMeta $projectRoot
    $versionFile = Join-Path (Get-DeployMetaPath $projectRoot) "version"
    $vLabel = Format-VersionLabel $version
    Set-Content -Path $versionFile -Value $vLabel -NoNewline

    # Spiegel naar .env
    $script:ProjectRoot = $projectRoot
    Set-EnvValue "PATCH_VERSION" $vLabel | Out-Null
}

function Sync-EnvFromDeployVersion($projectRoot) {
    # Bij watcher-start: zorg dat .env PATCH_VERSION matcht met .deploy/version.
    # Dit handelt het scenario af: PC2 doet git pull, .deploy/version is nu V324,
    # maar PC2's .env zegt nog V321. We zetten .env stilletjes goed.
    $script:ProjectRoot = $projectRoot
    $deployVersion = Get-DeployVersion $projectRoot
    if ($deployVersion -eq 0) { return @{ synced = $false; reason = "geen .deploy/version" } }

    $vLabel = Format-VersionLabel $deployVersion
    $envValue = Get-EnvValue "PATCH_VERSION"

    if ($envValue -eq $vLabel) {
        return @{ synced = $false; reason = "al in sync"; version = $vLabel }
    }

    $written = Set-EnvValue "PATCH_VERSION" $vLabel
    if ($written) {
        return @{ synced = $true; from = $envValue; to = $vLabel }
    }
    return @{ synced = $false; reason = "geen .env aanwezig" }
}

function Update-DeployChangelog($projectRoot, $version, $updateInfoRaw) {
    Initialize-DeployMeta $projectRoot
    $changelogPath = Join-Path (Get-DeployMetaPath $projectRoot) "changelog.md"

    if (-not (Test-Path $changelogPath)) {
        Set-Content $changelogPath "# Changelog`n"
    }

    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm"
    $hostname = $env:COMPUTERNAME
    $vLabel = Format-VersionLabel $version

    $entry = @"

## $vLabel - $timestamp _(deployed op $hostname)_

$updateInfoRaw

---
"@
    Add-Content -Path $changelogPath -Value $entry
}

function Generate-DeployContext($projectRoot, $project) {
    Initialize-DeployMeta $projectRoot
    $contextPath = Join-Path (Get-DeployMetaPath $projectRoot) "context.txt"
    $changelogPath = Join-Path (Get-DeployMetaPath $projectRoot) "changelog.md"
    $version = Get-DeployVersion $projectRoot
    $currentVersion = Format-VersionLabel $version
    $nextVersion = Format-VersionLabel ($version + 1)

    $script:ProjectRoot = $projectRoot
    $appVersion = Get-EnvValue "APP_VERSION"

    $recentUpdates = ""
    if (Test-Path $changelogPath) {
        $content = Get-Content $changelogPath -Raw
        $entries = $content -split '(?=## V\d{3})' | Where-Object { $_ -match '## V\d{3}' } | Select-Object -Last 5
        $recentUpdates = ($entries -join "`n").Trim()
    }

    $appVersionLine = if ($appVersion) { "App versie:    $appVersion (semver in .env)" } else { "App versie:    onbekend" }
    $branch = (Get-GitConfig).branch

    # Optioneel vast projectoverzicht: .deploy/overview.md wordt, indien aanwezig,
    # als sectie net onder de header in de context geinjecteerd. Zo overleeft een
    # projectbeschrijving elke autoregeneratie. Geen bestand -> identiek oud gedrag.
    $overviewPath = Join-Path (Get-DeployMetaPath $projectRoot) "overview.md"
    $overviewBlock = ""
    if (Test-Path $overviewPath) {
        $ov = (Get-Content $overviewPath -Raw)
        if (-not [string]::IsNullOrWhiteSpace($ov)) {
            $overviewBlock = "`n$($ov.Trim())`n"
        }
    }

    $context = @"
# PROJECT CONTEXT - $project
# Plak dit als eerste bericht in een nieuwe Claude chat
# ============================================================
$overviewBlock
## Project
Naam:          $project
Patch versie:  $currentVersion
Volgende zip:  $nextVersion
$appVersionLine

## Stack
Projectpad:    $projectRoot
Build:         npm run build
Branch:        $branch

## Deploy workflow
- Aangepaste bestanden worden aangeleverd als zip: $project-update-V###.zip
- Zip bevat ENKEL gewijzigde/nieuwe bestanden met volledige relatieve paden
- update-info.md verplicht in zip met omschrijving + migrations + handmatige stappen
- Auto-deploy via in-project watcher: ``npm run deploy:watch`` in PhpStorm
- Watcher detecteert zips in Downloads OF in deploy/incoming/
- Commit en push gebeurt handmatig via PhpStorm
- Op andere machine: pull eerst, dan watcher starten

## Zip naamconventie
{project}-update-V{###}.zip
Nummer is zero-padded 3 cijfers, loopt op per project.

## Instructies voor Claude
- Lever ALTIJD enkel gewijzigde bestanden aan in een zip
- Volg de naamconventie hierboven
- Voeg ALTIJD update-info.md toe met:
  ## Wijzigingen
  - bullet 1
  - bullet 2
  ## Migrations
  ## Seeders
  ## Handmatige stappen
- Behoud de volledige relatieve padstructuur in de zip
- Volgende zip: $nextVersion

## Recente updates
$recentUpdates
"@

    Set-Content -Path $contextPath -Value $context
}
