@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
for %%I in ("%~dp0.") do set "APP_FOLDER=%%~nxI"
echo ==========================================
echo   BUFF V14 FINAL - KURULUM
echo ==========================================
echo.
echo 1. XAMPP icinde Apache ve MySQL acik olmali.
echo 2. Veritabani tek dosyadan kurulur: database\BUFF_DATABASE_SETUP.sql
echo 3. Node.js 20+ ve FFmpeg tavsiye edilir.
echo.
where npm >nul 2>nul
if errorlevel 1 goto skip_npm
if not exist node_modules (
  echo Node paketleri kuruluyor...
  call npm install
  if errorlevel 1 echo UYARI: npm install tamamlanamadi. Daha sonra proje klasorunde npm install calistirin.
)
:skip_npm
start "" "http://localhost/%APP_FOLDER%/install.php"
echo.
echo Tarayicida BUFF V14 KURULUM BASARILI yazisini gordukten sonra SERVISLERI_BASLAT.bat dosyasini calistirin.
pause
