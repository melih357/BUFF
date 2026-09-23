@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
echo ==========================================
echo   BUFF V14 - REALTIME + WORKER
echo ==========================================
where node >nul 2>nul
if errorlevel 1 (
  echo Node.js bulunamadi. Node.js 20+ kurun.
  pause
  exit /b 1
)
if not exist node_modules (
  echo node_modules bulunamadi. npm install calistiriliyor...
  call npm install
  if errorlevel 1 (
    echo npm install basarisiz.
    pause
    exit /b 1
  )
)
start "BUFF V14 Realtime" cmd /k "cd /d %~dp0 && node backend\server.js"
start "BUFF V14 Worker" cmd /k "cd /d %~dp0 && node backend\worker.js"
echo Realtime sunucusu ve worker baslatildi.
echo XAMPP Apache ve MySQL de acik kalmali.
pause
