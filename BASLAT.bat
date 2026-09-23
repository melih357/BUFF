@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
for %%I in ("%~dp0.") do set "APP_FOLDER=%%~nxI"
echo BUFF V14 baslatiliyor...
call SERVISLERI_BASLAT.bat
start "" "http://localhost/%APP_FOLDER%/frontend/"
