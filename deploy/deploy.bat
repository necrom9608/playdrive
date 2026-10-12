@echo off
REM Playdrive uitrollen naar de Hetzner-server. Gebruik: deploy.bat  |  deploy.bat status  |  deploy.bat rollback
cd /d "%~dp0.."
if /i "%1"=="rollback" (
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy.ps1" -Rollback
) else if /i "%1"=="status" (
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy.ps1" -Status
) else (
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy.ps1"
)
echo.
pause
