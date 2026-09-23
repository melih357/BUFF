@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
for %%I in ("%~dp0.") do set "APP_FOLDER=%%~nxI"
echo ==========================================
echo   BUFF V14 - VERITABANI DOGRULAMA / ONARIM
echo ==========================================
echo.
echo Tek V14 SQL semasi yeniden calistirilabilir; mevcut veriler silinmez.
start "" "http://localhost/%APP_FOLDER%/install.php"
pause
