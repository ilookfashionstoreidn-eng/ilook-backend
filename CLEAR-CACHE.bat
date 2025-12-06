@echo off
echo.
echo ╔════════════════════════════════════════════════════════╗
echo ║   CLEAR SEMUA CACHE LARAVEL                            ║
echo ╚════════════════════════════════════════════════════════╝
echo.
echo [1/5] Clearing config cache...
php artisan config:clear
echo.
echo [2/5] Clearing application cache...
php artisan cache:clear
echo.
echo [3/5] Clearing route cache...
php artisan route:clear
echo.
echo [4/5] Clearing view cache...
php artisan view:clear
echo.
echo [5/5] Clearing all optimized files...
php artisan optimize:clear
echo.
echo ╔════════════════════════════════════════════════════════╗
echo ║   ✅ SEMUA CACHE SUDAH DIBERSIHKAN!                    ║
echo ╚════════════════════════════════════════════════════════╝
echo.
echo Sekarang:
echo 1. Stop Laragon (Stop All)
echo 2. Start Laragon (Start All)
echo 3. Login frontend
echo 4. Download barcode
echo.
pause


