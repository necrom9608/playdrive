# Playdrive Windows Dev Setup Script
# Run as Administrator (recommended)

Write-Host ""
Write-Host "=== Playdrive Dev Machine Setup ===" -ForegroundColor Cyan
Write-Host ""

# ----------------------------
# Helpers
# ----------------------------
function Test-Command($name) {
    return [bool](Get-Command $name -ErrorAction SilentlyContinue)
}

function Section($text) {
    Write-Host ""
    Write-Host "=== $text ===" -ForegroundColor Yellow
}

# ----------------------------
# Check Node
# ----------------------------
Section "Node.js"

if (Test-Command node) {
    node -v
    npm -v
} else {
    Write-Host "Node.js not found." -ForegroundColor Red
    Write-Host "Download: https://nodejs.org/"
}

# ----------------------------
# Check Git
# ----------------------------
Section "Git"

if (Test-Command git) {
    git --version
} else {
    Write-Host "Git not found." -ForegroundColor Red
    Write-Host "Download: https://git-scm.com/download/win"
}

# ----------------------------
# Check Rust
# ----------------------------
Section "Rust"

if (Test-Command rustc) {
    rustc -V
    cargo -V
} else {
    Write-Host "Rust not found." -ForegroundColor Red
    Write-Host "Download: https://rustup.rs/"
}

# ----------------------------
# Check PHP
# ----------------------------
Section "PHP"

if (Test-Command php) {
    php -v
} else {
    Write-Host "PHP not found (optional if using WAMP/XAMPP)." -ForegroundColor DarkYellow
}

# ----------------------------
# Check Composer
# ----------------------------
Section "Composer"

if (Test-Command composer) {
    composer -V
} else {
    Write-Host "Composer not found." -ForegroundColor Red
    Write-Host "Download: https://getcomposer.org/download/"
}

# ----------------------------
# Build Tools Reminder
# ----------------------------
Section "Visual Studio Build Tools"

Write-Host "Install manually if not installed:"
Write-Host "https://visualstudio.microsoft.com/visual-cpp-build-tools/"
Write-Host ""
Write-Host "Required workload:"
Write-Host "- Desktop development with C++"

# ----------------------------
# Project Install
# ----------------------------
Section "Project Install"

$projectPath = Read-Host "Enter Playdrive project path (or leave empty to skip)"

if ($projectPath -and (Test-Path $projectPath)) {

    Set-Location $projectPath

    if (Test-Path ".\\composer.json") {
        Write-Host ""
        Write-Host "Running composer install..."
        composer install
    }

    if (Test-Path ".\\package.json") {
        Write-Host ""
        Write-Host "Running npm install..."
        npm install
    }

    if (Test-Path ".\\desktop\\package.json") {
        Write-Host ""
        Write-Host "Installing desktop dependencies..."
        Set-Location ".\\desktop"
        npm install
        Set-Location ".."
    }

    Write-Host ""
    Write-Host "Project dependencies installed." -ForegroundColor Green
}
else {
    Write-Host "Skipped project install."
}

# ----------------------------
# Finish
# ----------------------------
Section "Done"

Write-Host "Useful commands:"
Write-Host ""
Write-Host "Laravel:"
Write-Host "php artisan serve"
Write-Host ""
Write-Host "Vite:"
Write-Host "npm run dev"
Write-Host ""
Write-Host "Desktop:"
Write-Host "cd desktop"
Write-Host "npm run tauri:dev"
Write-Host ""
