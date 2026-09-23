@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion
cd /d "%~dp0"
set "PHPBIN=php"
where php >nul 2>nul
if errorlevel 1 if exist "C:\xampp\php\php.exe" set "PHPBIN=C:\xampp\php\php.exe"
echo [1/3] PHP syntax kontrolu...
for /r %%F in (*.php) do (
  "%PHPBIN%" -l "%%F" >nul
  if errorlevel 1 (
    echo HATA: %%F
    "%PHPBIN%" -l "%%F"
    exit /b 1
  )
)
echo [2/3] JavaScript syntax kontrolu...
where node >nul 2>nul
if errorlevel 1 (
  echo UYARI: Node.js yok, JS kontrolu atlandi.
) else (
  for /r backend %%F in (*.js) do (
    node --check "%%F" >nul
    if errorlevel 1 (echo HATA: %%F & exit /b 1)
  )
  for /r frontend\assets\js %%F in (*.js) do (
    node --check "%%F" >nul
    if errorlevel 1 (echo HATA: %%F & exit /b 1)
  )
)
echo [3/3] V14 dosya/sema kontrolu...
if exist scripts\verify-static.js (
  node scripts\verify-static.js
  if errorlevel 1 exit /b 1
)
echo BUFF V14 statik kontrolleri basarili.
pause
