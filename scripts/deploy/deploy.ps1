# ============================================================
#  deploy.ps1  -  Patch deployer (single-project, in-project)
# ============================================================
#
# Wordt automatisch aangeroepen door watcher.ps1 zodra een zip in
# deploy/incoming/ verschijnt. Kan ook standalone:
#   pwsh scripts/deploy/deploy.ps1 -ZipPath path/to/zip.zip

param(
    [Parameter(Mandatory)][string]$ZipPath,
    [switch]$NonInteractive
)

$ErrorActionPreference = "Stop"
$scriptDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = (Resolve-Path (Join-Path $scriptDir "..\..")).Path
$deployDir   = Join-Path $projectRoot "deploy"
$logFile     = Join-Path $deployDir "watcher.log"

# Project-naam afleiden van de map. Voor playdrive = "playdrive".
$projectName = Split-Path -Leaf $projectRoot

. (Join-Path $scriptDir "git-helpers.ps1")
$script:ProjectRoot = $projectRoot

try {
    [Console]::OutputEncoding = [System.Text.UTF8Encoding]::new()
    [Console]::InputEncoding  = [System.Text.UTF8Encoding]::new()
} catch {}

# ============================================================
#  CLI helpers
# ============================================================

function Write-CY($txt, [switch]$NoNewline) { Write-Host $txt -ForegroundColor Cyan -NoNewline:$NoNewline }
function Write-GR($txt, [switch]$NoNewline) { Write-Host $txt -ForegroundColor Green -NoNewline:$NoNewline }
function Write-YL($txt, [switch]$NoNewline) { Write-Host $txt -ForegroundColor Yellow -NoNewline:$NoNewline }
function Write-RD($txt, [switch]$NoNewline) { Write-Host $txt -ForegroundColor Red -NoNewline:$NoNewline }
function Write-MG($txt, [switch]$NoNewline) { Write-Host $txt -ForegroundColor Magenta -NoNewline:$NoNewline }
function Write-GY($txt, [switch]$NoNewline) { Write-Host $txt -ForegroundColor DarkGray -NoNewline:$NoNewline }

function Write-Info($msg) { Write-CY "  > " -NoNewline; Write-Host $msg }
function Write-Ok($msg)   { Write-GR "  + " -NoNewline; Write-Host $msg }
function Write-Warn($msg) { Write-YL "  ! " -NoNewline; Write-Host $msg }
function Write-Err($msg)  { Write-RD "  x " -NoNewline; Write-Host $msg }

function Write-Step($num, $total, $msg) {
    Write-Host "  " -NoNewline
    Write-GY "[$num/$total] " -NoNewline
    Write-YL "... " -NoNewline
    Write-Host $msg.PadRight(50) -NoNewline
}

function Write-StepOk($extra = "OK")     { Write-GR "[+ $extra]" }
function Write-StepFail($reason = "FAIL"){ Write-RD "[x $reason]" }
function Write-StepSkip($reason = "skip"){ Write-GY "[$reason]" }

function Write-Log($level, $msg) {
    if (-not (Test-Path $deployDir)) { New-Item -ItemType Directory -Path $deployDir -Force | Out-Null }
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $logFile -Value "[$timestamp] [$level] $msg"
}

# ============================================================
#  Folder setup
# ============================================================

function Initialize-DeployFolders {
    @("incoming", "deployed", "failed", "backups") | ForEach-Object {
        $path = Join-Path $deployDir $_
        if (-not (Test-Path $path)) {
            New-Item -ItemType Directory -Path $path -Force | Out-Null
        }
    }
}

# ============================================================
#  Zip parsing
# ============================================================

function Parse-ZipName($zipName) {
    if ($zipName -match '^([a-z0-9-]+)-update-V(\d{3})\.zip$') {
        return @{
            project      = $Matches[1]
            version      = [int]$Matches[2]
            versionLabel = "V$($Matches[2])"
        }
    }
    return $null
}

# ============================================================
#  Update-info inlezen
# ============================================================

function Get-UpdateInfo($extractPath) {
    $infoPath = Join-Path $extractPath "update-info.md"
    if (-not (Test-Path $infoPath)) {
        return @{ raw = "" }
    }
    return @{ raw = (Get-Content $infoPath -Raw) }
}

# ============================================================
#  Migrations + seeders detectie
# ============================================================

function Detect-Migrations($extractPath) {
    $migPath = Join-Path $extractPath "database\migrations"
    if (-not (Test-Path $migPath)) { return @() }
    return Get-ChildItem -Path $migPath -Filter "*.php" -Recurse | ForEach-Object { $_.Name }
}

function Detect-Seeders($extractPath) {
    $seedPath = Join-Path $extractPath "database\seeders"
    if (-not (Test-Path $seedPath)) { return @() }
    return Get-ChildItem -Path $seedPath -Filter "*.php" -Recurse | ForEach-Object { $_.Name }
}

# ============================================================
#  Deploy flow helpers
# ============================================================

function Get-FilesToDeploy($extractPath) {
    $allFiles = Get-ChildItem -Path $extractPath -Recurse -File
    $allFiles | Where-Object {
        $rel = $_.FullName.Substring($extractPath.Length + 1)
        ($rel -ne "update-info.md") -and (-not $rel.StartsWith("_hooks\"))
    }
}

function Backup-Files($projectRoot, $filesToDeploy, $extractPath, $backupDir) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    $backedUp = 0
    $newFiles = @()

    foreach ($file in $filesToDeploy) {
        $rel = $file.FullName.Substring($extractPath.Length + 1)
        $existing = Join-Path $projectRoot $rel
        if (Test-Path $existing) {
            $backupTarget = Join-Path $backupDir $rel
            $backupTargetDir = Split-Path -Parent $backupTarget
            if (-not (Test-Path $backupTargetDir)) {
                New-Item -ItemType Directory -Path $backupTargetDir -Force | Out-Null
            }
            Copy-Item -Path $existing -Destination $backupTarget -Force
            $backedUp++
        } else {
            $newFiles += $rel
        }
    }

    $manifest = @{
        backedUpFiles = ($filesToDeploy | ForEach-Object { $_.FullName.Substring($extractPath.Length + 1) })
        newFiles      = $newFiles
        timestamp     = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
        projectRoot   = $projectRoot
    }
    $manifest | ConvertTo-Json -Depth 10 | Set-Content (Join-Path $backupDir "manifest.json")
    return $backedUp
}

function Copy-FilesToProject($filesToDeploy, $extractPath, $projectRoot) {
    foreach ($file in $filesToDeploy) {
        $rel = $file.FullName.Substring($extractPath.Length + 1)
        $target = Join-Path $projectRoot $rel
        $targetDir = Split-Path -Parent $target
        if (-not (Test-Path $targetDir)) {
            New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
        }
        Copy-Item -Path $file.FullName -Destination $target -Force
    }
}

function Run-Migrations($projectRoot, $migrations, $autoMigrate, $nonInteractive) {
    if ($migrations.Count -eq 0) { return "geen" }

    if (-not $autoMigrate) {
        if ($nonInteractive) {
            return "overgeslagen (non-interactive)"
        }
        Write-Host ""
        Write-YL "  ! MIGRATIONS GEVONDEN"
        Write-Host ""
        foreach ($m in $migrations) { Write-GY "    - $m" }
        Write-Host ""
        Write-Host "  " -NoNewline; Write-CY "[U]" -NoNewline; Write-Host " Uitvoeren"
        Write-Host "  " -NoNewline; Write-CY "[O]" -NoNewline; Write-Host " Overslaan"
        Write-Host "  " -NoNewline; Write-CY "[A]" -NoNewline; Write-Host " Annuleren (rollback)"
        Write-Host ""
        $choice = (Read-Host "  Keuze").ToUpper()
        switch ($choice) {
            "U" { }
            "O" { return "overgeslagen" }
            default { throw "GEANNULEERD bij migrations" }
        }
    }

    Push-Location $projectRoot
    try {
        php artisan migrate --force
        if ($LASTEXITCODE -ne 0) { throw "php artisan migrate gefaald" }
        return "uitgevoerd"
    } finally {
        Pop-Location
    }
}

function Run-Seeders($projectRoot, $seeders, $autoSeed, $nonInteractive) {
    if ($seeders.Count -eq 0) { return "geen" }
    if (-not $autoSeed) {
        if ($nonInteractive) { return "overgeslagen (non-interactive)" }
        Write-Host ""
        Write-YL "  ! SEEDERS GEVONDEN ($($seeders.Count))"
        $choice = Read-Host "  Seeders uitvoeren? [j/N]"
        if ($choice -ne "j") { return "overgeslagen" }
    }
    Push-Location $projectRoot
    try {
        php artisan db:seed --force
        if ($LASTEXITCODE -ne 0) { throw "php artisan db:seed gefaald" }
        return "uitgevoerd"
    } finally {
        Pop-Location
    }
}

function Run-Build($projectRoot, $buildCommand) {
    if ([string]::IsNullOrWhiteSpace($buildCommand)) {
        return "skip"
    }
    Push-Location $projectRoot
    try {
        $output = Invoke-Expression $buildCommand 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "$buildCommand gefaald (exit $LASTEXITCODE):`n$($output | Out-String)"
        }
        return "OK"
    } finally {
        Pop-Location
    }
}

function Run-Hooks($extractPath, $phase, $projectRoot) {
    $hookPath = Join-Path $extractPath "_hooks\$phase.ps1"
    if (-not (Test-Path $hookPath)) { return $false }
    Push-Location $projectRoot
    try {
        & $hookPath
        if ($LASTEXITCODE -ne 0) { throw "$phase hook gefaald" }
        return $true
    } finally {
        Pop-Location
    }
}

function Show-ToastNotification($title, $message, $isError = $false) {
    try {
        $iconType = if ($isError) { "Error" } else { "Info" }
        Add-Type -AssemblyName System.Windows.Forms -ErrorAction SilentlyContinue
        $balloon = New-Object System.Windows.Forms.NotifyIcon
        $balloon.Icon = [System.Drawing.SystemIcons]::$iconType
        $balloon.BalloonTipIcon = $iconType
        $balloon.BalloonTipTitle = $title
        $balloon.BalloonTipText = $message
        $balloon.Visible = $true
        $balloon.ShowBalloonTip(5000)
        Start-Sleep -Milliseconds 100
    } catch {}
}

function Invoke-Rollback($backupDir) {
    Write-Host ""
    Write-Warn "Rollback uitvoeren..."

    $manifestPath = Join-Path $backupDir "manifest.json"
    if (-not (Test-Path $manifestPath)) {
        Write-Err "Geen manifest gevonden"
        return $false
    }
    $manifest = Get-Content $manifestPath -Raw | ConvertFrom-Json
    $rollbackRoot = $manifest.projectRoot

    $restored = 0
    foreach ($rel in $manifest.backedUpFiles) {
        if ($manifest.newFiles -contains $rel) { continue }
        $backupFile = Join-Path $backupDir $rel
        $target = Join-Path $rollbackRoot $rel
        if (Test-Path $backupFile) {
            Copy-Item -Path $backupFile -Destination $target -Force
            $restored++
        }
    }

    $removed = 0
    foreach ($rel in $manifest.newFiles) {
        $target = Join-Path $rollbackRoot $rel
        if (Test-Path $target) {
            Remove-Item -Path $target -Force
            $removed++
        }
    }

    Write-Ok "$restored hersteld, $removed verwijderd"
    return $true
}

# ============================================================
#  HOOFD DEPLOY FLOW
# ============================================================

function Deploy-Zip {
    param([string]$ZipPath)

    if (-not (Test-Path $ZipPath)) {
        Write-Err "Zip niet gevonden: $ZipPath"
        return $false
    }

    Initialize-DeployFolders

    $zipName = Split-Path -Leaf $ZipPath
    $zipSize = "{0:N1} KB" -f ((Get-Item $ZipPath).Length / 1KB)

    $parsed = Parse-ZipName $zipName
    if ($null -eq $parsed) {
        Write-Err "Zipnaam volgt patroon niet: $zipName"
        return $false
    }

    # Project-check: zip moet voor dit project zijn
    if ($parsed.project -ne $projectName) {
        Write-Err "Zip is voor project '$($parsed.project)', niet voor '$projectName'"
        $failedDir = Join-Path $deployDir "failed"
        Move-Item -Path $ZipPath -Destination $failedDir -Force
        return $false
    }

    $version = $parsed.version
    $versionLabel = $parsed.versionLabel
    $gitCfg = Get-GitConfig

    if (-not (Test-Path $projectRoot)) {
        Write-Err "Project root bestaat niet: $projectRoot"
        return $false
    }

    $currentVersion = Get-DeployVersion $projectRoot

    # Auto-config uit .env
    $autoMigrate = Get-EnvBool "DEPLOY_AUTO_MIGRATE" $false
    $autoSeed    = Get-EnvBool "DEPLOY_AUTO_SEED"    $false
    $buildCommand = Get-EnvValue "DEPLOY_BUILD_COMMAND"
    if ([string]::IsNullOrWhiteSpace($buildCommand)) { $buildCommand = "npm run build" }

    # Banner
    Write-Host ""
    Write-CY "+-- NIEUWE ZIP GEDETECTEERD ----------------------------------+"
    Write-Host ""
    Write-Host "  Project    : " -NoNewline; Write-MG $projectName
    Write-Host "  Bestand    : $zipName"
    Write-Host "  Grootte    : $zipSize"
    Write-Host "  Versie     : " -NoNewline
    Write-MG (Format-VersionLabel $currentVersion) -NoNewline
    Write-Host "  ->  " -NoNewline
    Write-MG $versionLabel
    Write-CY "+-------------------------------------------------------------+"
    Write-Host ""

    Write-Log "INFO" "Deploy gestart: $zipName"

    # Versie-mismatch check
    $expected = $currentVersion + 1
    if ($version -ne $expected) {
        Write-Warn "Versie-mismatch: verwacht $(Format-VersionLabel $expected), gekregen $versionLabel"

        if ($version -le $currentVersion) {
            Write-Warn "Deze versie is al gedeployed (mogelijk via Git pull van andere machine)"
        }

        if ($NonInteractive) {
            Write-Err "Auto-deploy stopt bij mismatch"
            $failedDir = Join-Path $deployDir "failed"
            Move-Item -Path $ZipPath -Destination $failedDir -Force
            return $false
        }
        Write-Host ""
        Write-Host "  " -NoNewline; Write-CY "[D]" -NoNewline; Write-Host " Doorgaan"
        Write-Host "  " -NoNewline; Write-CY "[O]" -NoNewline; Write-Host " Overslaan"
        Write-Host ""
        $choice = (Read-Host "  Keuze").ToUpper()
        if ($choice -ne "D") {
            Move-Item -Path $ZipPath -Destination (Join-Path $deployDir "failed") -Force
            return $false
        }
    }

    $extractPath = Join-Path $env:TEMP "deploy-$projectName-$version-$(Get-Random)"
    New-Item -ItemType Directory -Path $extractPath -Force | Out-Null

    $totalSteps = 9
    $stepNum = 0
    $startTime = Get-Date
    $backupDir = $null
    $deployedCount = 0
    $migrationsResult = "geen"
    $seedersResult = "geen"
    $commitHash = "n/a"
    $gitEnabled = $gitCfg.enabled -and (Test-GitRepo $projectRoot)

    try {
        # STAP 1: Git pull
        $stepNum++
        Write-Step $stepNum $totalSteps "Git pull"
        if ($gitEnabled -and $gitCfg.autoPullBeforeDeploy) {
            $pullResult = Invoke-GitPull $projectRoot $gitCfg.branch
            if (-not $pullResult.success) {
                Write-StepFail
                throw "Git pull gefaald:`n$($pullResult.output)"
            }
            if ($pullResult.hasUpdates) {
                Write-StepOk "nieuwe commits"
            } else {
                Write-StepOk "up-to-date"
            }
        } else {
            Write-StepSkip
        }

        # Versie opnieuw checken na pull (kan gewijzigd zijn)
        $newCurrentVersion = Get-DeployVersion $projectRoot
        if ($newCurrentVersion -ne $currentVersion) {
            Write-Warn "Versie geupdatet door pull: $(Format-VersionLabel $currentVersion) -> $(Format-VersionLabel $newCurrentVersion)"
            $currentVersion = $newCurrentVersion
            if ($version -le $currentVersion) {
                throw "Deze versie ($versionLabel) is al gedeployed via Git pull. Zip wordt overgeslagen."
            }
        }

        # STAP 2: Zip uitpakken
        $stepNum++
        Write-Step $stepNum $totalSteps "Zip uitpakken"
        Expand-Archive -Path $ZipPath -DestinationPath $extractPath -Force
        Write-StepOk

        # STAP 3: Update info inlezen
        $stepNum++
        Write-Step $stepNum $totalSteps "Update info inlezen"
        $updateInfo = Get-UpdateInfo $extractPath
        if ($updateInfo.raw -ne "") { Write-StepOk "gevonden" } else { Write-StepSkip "geen" }

        # STAP 4: Backup
        $stepNum++
        Write-Step $stepNum $totalSteps "Backup maken"
        $filesToDeploy = Get-FilesToDeploy $extractPath
        $backupDir = Join-Path $deployDir "backups\$(Format-VersionLabel $currentVersion)-pre-$versionLabel"
        $backedUp = Backup-Files $projectRoot $filesToDeploy $extractPath $backupDir
        Write-StepOk "$backedUp files"

        # STAP 5: Pre-deploy hook
        $stepNum++
        Write-Step $stepNum $totalSteps "Pre-deploy hook"
        $hookRan = Run-Hooks $extractPath "pre-deploy" $projectRoot
        if ($hookRan) { Write-StepOk } else { Write-StepSkip }

        # STAP 6: Files kopieren
        $stepNum++
        Write-Step $stepNum $totalSteps "Bestanden kopieren ($($filesToDeploy.Count) files)"
        Copy-FilesToProject $filesToDeploy $extractPath $projectRoot
        $deployedCount = $filesToDeploy.Count
        Write-StepOk

        # STAP 7: Migrations + seeders
        $stepNum++
        Write-Step $stepNum $totalSteps "Migrations & seeders"
        $migrations = Detect-Migrations $extractPath
        $seeders = Detect-Seeders $extractPath
        if ($migrations.Count -eq 0 -and $seeders.Count -eq 0) {
            Write-StepSkip "geen"
        } else {
            Write-Host ""
            $migrationsResult = Run-Migrations $projectRoot $migrations $autoMigrate $NonInteractive
            $seedersResult = Run-Seeders $projectRoot $seeders $autoSeed $NonInteractive
            Write-Ok "Migrations: $migrationsResult"
            Write-Ok "Seeders: $seedersResult"
        }

        # STAP 8: Build
        $stepNum++
        Write-Step $stepNum $totalSteps "Build draaien"
        $buildResult = Run-Build $projectRoot $buildCommand
        if ($buildResult -eq "skip") { Write-StepSkip "geen build" } else { Write-StepOk }

        Run-Hooks $extractPath "post-deploy" $projectRoot | Out-Null

        # Update .deploy/ metadata + .env spiegel
        Set-DeployVersion $projectRoot $version
        Update-DeployChangelog $projectRoot $version $updateInfo.raw
        Generate-DeployContext $projectRoot $projectName

        # STAP 9: Git commit (lokaal, geen push)
        $stepNum++
        Write-Step $stepNum $totalSteps "Git commit (lokaal)"
        if ($gitEnabled -and $gitCfg.autoCommit) {
            $commitMsg = Format-CommitMessage $version $updateInfo.raw
            $commitResult = Invoke-GitCommit $projectRoot $commitMsg
            if ($commitResult.success) {
                if ($commitResult.nothing) {
                    Write-StepSkip "niets te committen"
                } else {
                    $commitHash = $commitResult.hash
                    Write-StepOk "$commitHash"
                }
            } else {
                Write-Warn "Commit gefaald: $($commitResult.message)"
                Write-StepFail
            }
        } else {
            Write-StepSkip
        }

        # Zip naar deployed
        $deployedDir = Join-Path $deployDir "deployed"
        $deployedZipName = $zipName -replace '\.zip$', '.deployed.zip'
        Move-Item -Path $ZipPath -Destination (Join-Path $deployedDir $deployedZipName) -Force

        Remove-Item -Path $extractPath -Recurse -Force -ErrorAction SilentlyContinue

        $duration = ((Get-Date) - $startTime).TotalSeconds
        $durationStr = "{0:N1}s" -f $duration

        Write-Host ""
        Write-GR "+-- DEPLOY GESLAAGD ------------------------------------------+"
        Write-Host ""
        Write-Host "  " -NoNewline
        Write-MG "$projectName $versionLabel" -NoNewline
        Write-Host " is live"
        Write-Host "  $deployedCount files . migrations: $migrationsResult . commit: $commitHash . $durationStr"
        if ($gitEnabled) {
            Write-GY "  Push wanneer klaar via PhpStorm"
        }
        Write-Host ""
        Write-GR "+-------------------------------------------------------------+"
        Write-Host ""

        Write-Log "OK" "Deploy succesvol: $projectName $versionLabel ($durationStr, commit $commitHash)"
        Show-ToastNotification "Deploy geslaagd" "$projectName $versionLabel - $deployedCount files in $durationStr"
        return $true

    } catch {
        $errorMsg = $_.Exception.Message
        Write-Host ""
        Write-StepFail "FAIL"
        Write-Host ""
        Write-Err "Fout: $errorMsg"
        Write-Log "ERR" "Deploy gefaald: $projectName $versionLabel - $errorMsg"

        if ($null -ne $backupDir -and (Test-Path $backupDir)) {
            Invoke-Rollback $backupDir | Out-Null
        }

        $failedDir = Join-Path $deployDir "failed"
        if (Test-Path $ZipPath) {
            Move-Item -Path $ZipPath -Destination $failedDir -Force
        }
        Remove-Item -Path $extractPath -Recurse -Force -ErrorAction SilentlyContinue

        Write-Host ""
        Write-RD "+-- DEPLOY GEFAALD -------------------------------------------+"
        Write-Host ""
        Write-Host "  Project   : $projectName $versionLabel"
        $shortMsg = if ($errorMsg.Length -gt 50) { $errorMsg.Substring(0, 50) + "..." } else { $errorMsg }
        Write-Host "  Reden     : $shortMsg"
        Write-Host "  Logfile   : $logFile"
        Write-Host ""
        Write-RD "+-------------------------------------------------------------+"
        Write-Host ""

        Show-ToastNotification "Deploy gefaald" "$projectName $versionLabel - $errorMsg" $true
        return $false
    }
}

Deploy-Zip -ZipPath $ZipPath
