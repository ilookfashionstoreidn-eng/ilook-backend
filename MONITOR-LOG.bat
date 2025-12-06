@echo off
echo.
echo ═══════════════════════════════════════════════════════════
echo    MONITORING LARAVEL LOG - REAL TIME
echo ═══════════════════════════════════════════════════════════
echo.
echo Tekan Ctrl+C untuk stop monitoring
echo.
echo ───────────────────────────────────────────────────────────
echo.

cd D:\Frontend\ilook-backend
powershell -Command "Get-Content storage\logs\laravel.log -Wait -Tail 20"

