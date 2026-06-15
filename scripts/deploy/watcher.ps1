# ============================================================
#  watcher.ps1  -  File watcher (single-project, in-project)
# ============================================================
#
# Start via:  npm run deploy:watch
#
# Wat hij doet:
# - Synct .env PATCH_VERSION met .deploy/version (de waarheid)
# - Doet een git pull bij start (als .env DEPLOY_GIT_AUTOPULL_ON_START=true)
# - Watcht deploy/incoming/ voor zips → triggert deploy.ps1
# - Watcht je Downloads-folder voor {project}-update-V###.zip
#   → verplaatst die automatisch naar deploy/incoming/
#   (uit te zetten met DEPLOY_WATCH_DOWNLOADS=false)

$ErrorActionPreference = "Continue"
$scriptDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = (Resolve-Path (Join-Path $scriptDir "..\..")).Path
$deployDir   = Join-Path $projectRoot "deploy"
$incomingDir = Join-Path $deployDir "incoming"
$deployScript = Join-Path $scriptDir "deploy.ps1"
$logFile     = Join-Path $deployDir "watcher.log"

$projectName = Split-Path -Leaf $projectRoot

. (Join-Path $scriptDir "git-helpers.ps1")
$script:ProjectRoot = $projectRoot

try {
    [Console]::OutputEncoding = [System.Text.UTF8Encoding]::new()
    [Console]::InputEncoding  = [System.Text.UTF8Encoding]::new()
} catch {}

# ============================================================
#  Banner
# ============================================================

function Show-Banner {
    Clear-Host
    Write-Host ""
    Write-Host "  ============================================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "   ____  _____ ____  _     ___  __   __" -ForegroundColor Yellow
    Write-Host "  |  _ \| ____|  _ \| |   / _ \ \ \ / /" -ForegroundColor Yellow
    Write-Host "  | | | |  _| | |_) | |  | | | | \ V / " -ForegroundColor Yellow
    Write-Host "  | |_| | |___|  __/| |__| |_| |  | |  " -ForegroundColor Yellow
    Write-Host "  |____/|_____|_|   |_____\___/   |_|  " -ForegroundColor Yellow
    Write-Host ""
    Write-Host "        $projectName  -  in-project deploy watcher" -ForegroundColor DarkGray
    Write-Host ""
    Write-Host "  ============================================================" -ForegroundColor Cyan
    Write-Host ""
}

function Initialize-DeployFolders {
    @("incoming", "deployed", "failed", "backups") | ForEach-Object {
        $path = Join-Path $deployDir $_
        if (-not (Test-Path $path)) {
            New-Item -ItemType Directory -Path $path -Force | Out-Null
        }
    }
}

# ============================================================
#  Downloads folder
# ============================================================

function Get-DownloadsPath {
    $custom = Get-EnvValue "DEPLOY_DOWNLOADS_PATH"
    if (-not [string]::IsNullOrWhiteSpace($custom)) { return $custom }
    return Join-Path $env:USERPROFILE "Downloads"
}

function Test-PatchZipName($name) {
    return $name -match "^$([regex]::Escape($projectName))-update-V\d{3}\.zip$"
}

function Move-PatchZipToIncoming($zipPath) {
    $zipName = Split-Path -Leaf $zipPath
    if (-not (Test-PatchZipName $zipName)) { return $null }

    $target = Join-Path $incomingDir $zipName

    if (Test-Path $target) {
        return @{ success = $false; reason = "bestaat al in incoming" }
    }

    try {
        Move-Item -Path $zipPath -Destination $target -Force
        return @{ success = $true; target = $target }
    } catch {
        return @{ success = $false; reason = $_.Exception.Message }
    }
}

# ============================================================
#  Status
# ============================================================

function Show-Status {
    $watchDownloads = Get-EnvBool "DEPLOY_WATCH_DOWNLOADS" $true

    Write-Host "  [" -NoNewline
    Write-Host "*" -ForegroundColor Green -NoNewline
    Write-Host "] " -NoNewline
    Write-Host "Watcher actief voor " -ForegroundColor White -NoNewline
    Write-Host $projectName -ForegroundColor Magenta
    Write-Host ""

    Write-Host "  Project   : " -ForegroundColor DarkGray -NoNewline
    Write-Host $projectRoot -ForegroundColor White

    $version = Get-DeployVersion $projectRoot
    $vLabel = if ($version -gt 0) { Format-VersionLabel $version } else { "geen" }
    Write-Host "  Patch     : " -ForegroundColor DarkGray -NoNewline
    Write-Host $vLabel -ForegroundColor White -NoNewline

    $envVersion = Get-EnvValue "PATCH_VERSION"
    if ($envVersion -and $envVersion -ne $vLabel) {
        Write-Host "  (.env zegt $envVersion - mismatch)" -ForegroundColor Yellow
    } else {
        Write-Host ""
    }

    $appVersion = Get-EnvValue "APP_VERSION"
    if ($appVersion) {
        Write-Host "  App       : " -ForegroundColor DarkGray -NoNewline
        Write-Host $appVersion -ForegroundColor White
    }

    # Git status
    $gitStatus = Get-GitStatus $projectRoot
    if ($null -ne $gitStatus) {
        Write-Host "  Branch    : " -ForegroundColor DarkGray -NoNewline
        Write-Host $gitStatus.branch -ForegroundColor White -NoNewline

        $parts = @()
        if ($gitStatus.uncommitted -gt 0) { $parts += "$($gitStatus.uncommitted) wijz." }
        if ($gitStatus.ahead -gt 0)       { $parts += "$($gitStatus.ahead) niet gepusht" }
        if ($gitStatus.behind -gt 0)      { $parts += "$($gitStatus.behind) achter" }

        if ($parts.Count -eq 0) {
            Write-Host "  (in sync)" -ForegroundColor Green
        } else {
            $statusLine = $parts -join ", "
            $color = if ($gitStatus.behind -gt 0) { "Yellow" } else { "Cyan" }
            Write-Host "  ($statusLine)" -ForegroundColor $color
        }
    }

    Write-Host ""
    Write-Host "  Wachten op nieuwe zips... (Ctrl+C om te stoppen)" -ForegroundColor DarkGray
    Write-Host "    monitoring: $incomingDir" -ForegroundColor DarkGray
    if ($watchDownloads) {
        $downloadsPath = Get-DownloadsPath
        Write-Host "    monitoring: $downloadsPath" -ForegroundColor DarkGray
    } else {
        Write-Host "    Downloads-folder watcher: uitgeschakeld" -ForegroundColor DarkGray
    }
    Write-Host ""
}

# ============================================================
#  Sync .env op startup
# ============================================================

function Sync-Versions {
    $result = Sync-EnvFromDeployVersion $projectRoot
    if ($result.synced) {
        Write-Host "  [.env sync] " -ForegroundColor Cyan -NoNewline
        Write-Host "PATCH_VERSION " -NoNewline
        Write-Host "$($result.from)" -ForegroundColor DarkGray -NoNewline
        Write-Host " -> " -NoNewline
        Write-Host "$($result.to)" -ForegroundColor White
        Write-Host ""
    }
}

# ============================================================
#  Git pull bij start
# ============================================================

function Pull-OnStart {
    $gitCfg = Get-GitConfig
    if (-not $gitCfg.enabled -or -not $gitCfg.autoPullOnStart) { return }
    if (-not (Test-GitRepo $projectRoot)) { return }

    Write-Host "  Git pull bij start..." -ForegroundColor White -NoNewline

    $result = Invoke-GitPull $projectRoot $gitCfg.branch
    if ($result.success) {
        if ($result.hasUpdates) {
            Write-Host "  nieuwe commits opgehaald" -ForegroundColor Green
            # Re-sync .env na pull
            $syncResult = Sync-EnvFromDeployVersion $projectRoot
            if ($syncResult.synced) {
                Write-Host "  PATCH_VERSION bijgewerkt: $($syncResult.from) -> $($syncResult.to)" -ForegroundColor Cyan
            }
        } else {
            Write-Host "  up-to-date" -ForegroundColor DarkGray
        }
    } else {
        Write-Host "  pull faalde" -ForegroundColor Red
        $shortErr = $result.output -split "`n" | Select-Object -First 1
        Write-Host "    $shortErr" -ForegroundColor DarkGray
    }
    Write-Host ""
}

# ============================================================
#  File watcher
# ============================================================

function Start-Watcher {
    $watchDownloads = Get-EnvBool "DEPLOY_WATCH_DOWNLOADS" $true
    $downloadsPath = Get-DownloadsPath

    # Watcher 1: incoming folder
    $watcherIncoming = New-Object System.IO.FileSystemWatcher
    $watcherIncoming.Path = $incomingDir
    $watcherIncoming.Filter = "*.zip"
    $watcherIncoming.IncludeSubdirectories = $false
    $watcherIncoming.EnableRaisingEvents = $true

    # Watcher 2: Downloads folder (optioneel)
    $watcherDownloads = $null
    if ($watchDownloads -and (Test-Path $downloadsPath)) {
        $watcherDownloads = New-Object System.IO.FileSystemWatcher
        $watcherDownloads.Path = $downloadsPath
        $watcherDownloads.Filter = "*.zip"
        $watcherDownloads.IncludeSubdirectories = $false
        $watcherDownloads.EnableRaisingEvents = $true
    }

    $script:pendingZips      = New-Object System.Collections.Queue
    $script:pendingDownloads = New-Object System.Collections.Queue
    $script:processing = $false

    $actionIncoming = {
        $path = $Event.SourceEventArgs.FullPath
        $name = $Event.SourceEventArgs.Name
        if ($name -match '\.deployed\.zip$') { return }
        $script:pendingZips.Enqueue($path)
    }

    $script:expectedPattern = "^$([regex]::Escape($projectName))-update-V\d{3}\.zip$"
    $actionDownloads = {
        $path = $Event.SourceEventArgs.FullPath
        $name = $Event.SourceEventArgs.Name
        if ($name -notmatch $script:expectedPattern) { return }
        if ($name -match '\.deployed\.zip$') { return }
        $script:pendingDownloads.Enqueue($path)
    }

    Register-ObjectEvent -InputObject $watcherIncoming -EventName "Created" -Action $actionIncoming | Out-Null
    Register-ObjectEvent -InputObject $watcherIncoming -EventName "Renamed" -Action $actionIncoming | Out-Null
    if ($null -ne $watcherDownloads) {
        Register-ObjectEvent -InputObject $watcherDownloads -EventName "Created" -Action $actionDownloads | Out-Null
        Register-ObjectEvent -InputObject $watcherDownloads -EventName "Renamed" -Action $actionDownloads | Out-Null
    }

    # Initiele scan: incoming
    Get-ChildItem -Path $incomingDir -Filter "*.zip" -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -notmatch '\.deployed\.zip$' } |
        ForEach-Object { $script:pendingZips.Enqueue($_.FullName) }

    # Initiele scan: Downloads
    if ($watchDownloads -and (Test-Path $downloadsPath)) {
        Get-ChildItem -Path $downloadsPath -Filter "*.zip" -ErrorAction SilentlyContinue |
            Where-Object {
                (Test-PatchZipName $_.Name) -and ($_.Name -notmatch '\.deployed\.zip$')
            } | ForEach-Object {
                $script:pendingDownloads.Enqueue($_.FullName)
            }
    }

    while ($true) {
        # Eerst: Downloads queue verwerken (verplaatsen naar incoming)
        while ($script:pendingDownloads.Count -gt 0) {
            $downloadPath = $script:pendingDownloads.Dequeue()

            Start-Sleep -Seconds 2
            if (-not (Test-Path $downloadPath)) { continue }

            $ready = $false
            $attempts = 0
            while (-not $ready -and $attempts -lt 10) {
                try {
                    $stream = [System.IO.File]::Open($downloadPath, 'Open', 'Read', 'None')
                    $stream.Close()
                    $ready = $true
                } catch {
                    Start-Sleep -Seconds 1
                    $attempts++
                }
            }

            if ($ready) {
                $zipName = Split-Path -Leaf $downloadPath
                Write-Host ""
                Write-Host "  > Patch zip in Downloads: " -ForegroundColor Cyan -NoNewline
                Write-Host $zipName -ForegroundColor Magenta

                $moveResult = Move-PatchZipToIncoming $downloadPath
                if ($moveResult.success) {
                    Write-Host "    + verplaatst naar deploy/incoming/" -ForegroundColor Green
                    # Incoming-watcher pikt het nu zelf op
                } else {
                    Write-Host "    ! verplaatsen overgeslagen: $($moveResult.reason)" -ForegroundColor Yellow
                }
                Write-Host ""
            }
        }

        # Dan: incoming queue verwerken (echte deploy)
        if ($script:pendingZips.Count -gt 0 -and -not $script:processing) {
            $script:processing = $true
            $zipPath = $script:pendingZips.Dequeue()

            Start-Sleep -Seconds 3

            if (Test-Path $zipPath) {
                $ready = $false
                $attempts = 0
                while (-not $ready -and $attempts -lt 10) {
                    try {
                        $stream = [System.IO.File]::Open($zipPath, 'Open', 'Read', 'None')
                        $stream.Close()
                        $ready = $true
                    } catch {
                        Start-Sleep -Seconds 1
                        $attempts++
                    }
                }

                if ($ready) {
                    try {
                        & $deployScript -ZipPath $zipPath -NonInteractive:$false
                    } catch {
                        Write-Host ""
                        Write-Host "  x Deploy crashte: $_" -ForegroundColor Red
                        Write-Host ""
                    }

                    Write-Host ""
                    Write-Host "  ------------------------------------------------------------" -ForegroundColor DarkGray
                    Show-Status
                }
            }
            $script:processing = $false
        }

        Start-Sleep -Milliseconds 500
    }
}

# ============================================================
#  Main
# ============================================================

Show-Banner
Initialize-DeployFolders
Sync-Versions
Pull-OnStart
Show-Status

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
if (-not (Test-Path $deployDir)) { New-Item -ItemType Directory -Path $deployDir -Force | Out-Null }
Add-Content -Path $logFile -Value "[$timestamp] [INFO] Watcher gestart voor $projectName"

try {
    Start-Watcher
} catch {
    Write-Host ""
    Write-Host "  x Watcher fout: $_" -ForegroundColor Red
    Add-Content -Path $logFile -Value "[$timestamp] [ERR] Watcher crash: $_"
    Read-Host "  Druk Enter om af te sluiten"
}
